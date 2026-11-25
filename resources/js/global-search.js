document.addEventListener('alpine:init', () => {
    Alpine.data('puzzleGlobalSearch', (settings, wire, commands, labels) => ({
    settings,
    wire,
    commands: commands || [],
    labels,
    isOpen: false,
    step: 'search',
    query: '',
    items: [],
    selectedIndex: 0,
    loading: false,
    timer: null,
    requestId: 0,
    commandId: null,
    dependencies: [],
    dependencyIndex: 0,
    values: {},

    get placeholder() {
        if (this.step === 'dependency') {
            return this.dependencies[this.dependencyIndex]?.placeholder
                || this.labels.placeholder;
        }

        if (this.step === 'commands') {
            return this.labels.placeholder_command;
        }

        return this.settings.ui.placeholder || this.labels.placeholder;
    },

    get emptyMessage() {
        if (this.step === 'commands') {
            return this.labels.no_commands;
        }

        const min = Number(
            this.settings.search.min_characters ?? 2
        );

        return this.query.trim().length < min
            ? this.labels.type_to_search
            : this.labels.no_results;
    },

    isIconClass(icon) {
        return typeof icon === 'string' && (
            icon.includes(' ')
            || icon.includes('fa-')
            || icon.includes('bi-')
            || icon.includes('ki-')
        );
    },

    decorate(results) {
        let last = null;

        return (results || []).map(item => {
            const category = item.category || null;
            const showCategory = !!category && category !== last;

            if (category) {
                last = category;
            }

            return { ...item, showCategory };
        });
    },

    matchesShortcut(event) {
        const keyboard = this.settings.keyboard || {};

        if (keyboard.enabled === false || !keyboard.shortcut) {
            return false;
        }

        const parts = keyboard.shortcut.toLowerCase().split('+');
        const key = parts.pop();
        const mac = /Mac|iPhone|iPad/.test(navigator.platform);
        const mod = parts.includes('mod');

        return event.key.toLowerCase() === key
            && event.ctrlKey === (
                parts.includes('ctrl') || (mod && !mac)
            )
            && event.metaKey === (
                parts.includes('meta') || (mod && mac)
            )
            && event.altKey === parts.includes('alt')
            && event.shiftKey === parts.includes('shift');
    },

    handleShortcut(event) {
        if (!this.matchesShortcut(event)) return;

        if (this.settings.keyboard.prevent_default !== false) {
            event.preventDefault();
        }

        event.stopPropagation();

        this.isOpen ? this.close() : this.open();
    },

    focusInput() {
        this.$nextTick(() => {
            this.$refs.searchInput?.focus();
        });
    },

    resetResults() {
        clearTimeout(this.timer);
        this.requestId++;
        this.items = [];
        this.selectedIndex = 0;
        this.loading = false;
    },

    open() {
        this.isOpen = true;
        this.step = 'search';
        this.query = '';
        this.commandId = null;
        this.dependencies = [];
        this.dependencyIndex = 0;
        this.values = {};
        this.resetResults();
        this.focusInput();
    },

    close() {
        this.isOpen = false;
        this.resetResults();
        this.query = '';
        this.step = 'search';
        this.commandId = null;
        this.dependencies = [];
        this.dependencyIndex = 0;
        this.values = {};
    },

    showCommands(query) {
        const q = query.toLowerCase();

        const commands = this.commands.filter(command => {
            if (q === '') {
                return true;
            }

            const haystack = [
                command.name,
                command.description,
                ...(command.keywords || []),
            ].join(' ').toLowerCase();

            return haystack.includes(q);
        }).map(command => ({
            id: command.id,
            title: command.name,
            description: command.description,
            icon: command.icon,
            hasDependencies: command.has_dependencies,
            category: this.labels.commands,
        }));

        this.items = this.decorate(commands);
        this.selectedIndex = 0;
        this.loading = false;
    },

    scheduleSearch() {
        clearTimeout(this.timer);

        const id = ++this.requestId;
        const delay = Math.max(
            0,
            Number(this.settings.search.debounce ?? 200)
        );
        const query = this.query.trim();

        if (this.step !== 'dependency' && query.startsWith('>')) {
            this.step = 'commands';
            this.showCommands(query.slice(1).trim());
            return;
        }

        if (this.step === 'commands') {
            this.step = 'search';
        }

        const min = Number(this.settings.search.min_characters ?? 2);

        if (query.length < min) {
            this.items = [];
            this.selectedIndex = 0;
            this.loading = false;
            return;
        }

        this.timer = setTimeout(() => this.runSearch(id, query), delay);
    },

    async runSearch(id, query) {
        if (id !== this.requestId || !this.isOpen) return;

        this.loading = true;

        try {
            let results;

            if (this.step === 'dependency') {
                const dependency = this.dependencies[this.dependencyIndex];

                results = await this.wire.searchDependency(
                    this.commandId,
                    dependency.name,
                    query
                );
            } else {
                results = await this.wire.searchAll(query);
            }

            if (id !== this.requestId || !this.isOpen) return;

            this.items = this.decorate(results);
            this.selectedIndex = 0;
        } catch (error) {
            if (id === this.requestId) {
                console.error('Global Search:', error);
            }
        } finally {
            if (id === this.requestId) {
                this.loading = false;
            }
        }
    },

    move(direction) {
        if (!this.items.length) return;

        this.selectedIndex = (
            this.selectedIndex + direction + this.items.length
        ) % this.items.length;

        this.$nextTick(() => {
            document.querySelector('.ps-search__result--active')
                ?.scrollIntoView({ block: 'nearest' });
        });
    },

    select() {
        const item = this.items[this.selectedIndex];

        if (item) this.choose(item);
    },

    opensInNewTab(event) {
        if (!event) return false;

        return event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || event.button !== 0;
    },

    async choose(item, event = null) {
        if (this.step === 'commands') {
            await this.selectCommand(item);
            return;
        }

        if (this.step === 'dependency') {
            const dependency = this.dependencies[this.dependencyIndex];

            this.values[dependency.name] = item.result_id ?? item.id;
            this.dependencyIndex++;

            if (this.dependencyIndex < this.dependencies.length) {
                this.query = '';
                this.resetResults();
                this.focusInput();
            } else {
                await this.execute();
            }

            return;
        }

        if (item.url) {
            if (this.opensInNewTab(event)) {
                return;
            }

            event?.preventDefault();
            window.location.assign(item.url);
            return;
        }

        if (item.command_id && item.result_id !== undefined && item.result_id !== null) {
            await this.openResult(item);
        }
    },

    async openResult(item) {
        const id = ++this.requestId;
        this.loading = true;

        try {
            const url = await this.wire.openResult(
                item.command_id,
                item.result_id
            );

            if (id !== this.requestId || !this.isOpen) return;

            if (url) {
                window.location.assign(url);
            } else {
                this.close();
            }
        } catch (error) {
            console.error('Global Search:', error);
        } finally {
            if (id === this.requestId) {
                this.loading = false;
            }
        }
    },

    async selectCommand(item) {
        this.resetResults();
        this.loading = true;

        const id = this.requestId;
        const commandId = item.id;

        try {
            const dependencies =
                await this.wire.dependencies(commandId);

            if (id !== this.requestId || !this.isOpen) return;

            this.commandId = commandId;
            this.dependencies = dependencies;
            this.dependencyIndex = 0;
            this.values = {};

            if (!dependencies.length) {
                await this.execute();
                return;
            }

            this.step = 'dependency';
            this.query = '';
            this.items = [];
            this.focusInput();
        } catch (error) {
            console.error('Global Search:', error);
        } finally {
            if (id === this.requestId) {
                this.loading = false;
            }
        }
    },

    back() {
        if (this.step === 'dependency' && this.dependencyIndex > 0) {
            this.dependencyIndex--;
            delete this.values[
                this.dependencies[this.dependencyIndex].name
            ];
            this.query = '';
            this.resetResults();
            this.focusInput();
            return;
        }

        this.step = 'search';
        this.commandId = null;
        this.dependencies = [];
        this.dependencyIndex = 0;
        this.values = {};
        this.query = '';
        this.resetResults();
        this.focusInput();
    },

    async execute() {
        const commandId = this.commandId;
        const values = { ...this.values };

        this.resetResults();
        this.loading = true;

        const id = this.requestId;

        try {
            const url = await this.wire.executeCommand(
                commandId,
                values
            );

            if (id !== this.requestId || !this.isOpen) {
                return;
            }

            if (url) {
                window.location.assign(url);
            } else {
                this.close();
            }
        } catch (error) {
            console.error('Global Search execution:', error);
        } finally {
            if (id === this.requestId) {
                this.loading = false;
            }
        }
    }
    }));
});
