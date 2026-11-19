<div
    x-data="puzzleGlobalSearch(@js($settings), $wire, @js($commands))"
    @keydown.window="handleShortcut($event)"
    @global-search-open.window="open()"
    @global-search-close.window="close()"
    class="ps-search"
>
    @once
        <style>
            {!! file_get_contents($globalSearchCssPath) !!}
        </style>
    @endonce

    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-cloak
            x-transition:enter="ps-search--enter"
            x-transition:enter-start="ps-search--enter-start"
            x-transition:enter-end="ps-search--enter-end"
            x-transition:leave="ps-search--leave"
            x-transition:leave-start="ps-search--leave-start"
            x-transition:leave-end="ps-search--leave-end"
            class="ps-search__overlay"
            data-theme="{{ $settings['ui']['theme'] ?? 'auto' }}"
            @keydown.escape.window="close()"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter.prevent="select()"
        >
            <div
                class="ps-search__backdrop"
                @click="close()"
            ></div>

            <div
                class="ps-search__dialog"
                role="dialog"
                aria-modal="true"
                aria-label="Buscador global"
            >
                <div class="ps-search__header">
                    <button
                        x-show="step === 'dependency'"
                        type="button"
                        class="ps-search__back"
                        @click="back()"
                        aria-label="Volver"
                    >
                        ←
                    </button>

                    <svg
                        x-show="step !== 'dependency'"
                        class="ps-search__icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>

                    <input
                        x-ref="searchInput"
                        x-model="query"
                        @input="scheduleSearch()"
                        type="search"
                        class="ps-search__input"
                        :placeholder="placeholder"
                        autocomplete="off"
                        aria-label="Buscar"
                    >

                    <span
                        class="ps-search__spinner"
                        x-show="loading"
                        x-cloak
                        role="status"
                        aria-label="Buscando"
                    ></span>

                    <button
                        type="button"
                        class="ps-search__kbd"
                        @click="close()"
                        aria-label="Cerrar"
                    >ESC</button>
                </div>

                <div class="ps-search__results">
                    <template x-if="loading && items.length === 0">
                        <div class="ps-search__message">
                            Buscando...
                        </div>
                    </template>

                    <template x-if="!loading && items.length === 0">
                        <div
                            class="ps-search__message"
                            x-text="emptyMessage"
                        ></div>
                    </template>

                    <template x-for="(item, index) in items" :key="item.id">
                        <div>
                            <div
                                class="ps-search__group-title"
                                x-show="item.showCategory"
                                x-text="item.category"
                            ></div>

                            <button
                                type="button"
                                class="ps-search__result"
                                :class="{
                                    'ps-search__result--active':
                                        selectedIndex === index
                                }"
                                @mouseenter="selectedIndex = index"
                                @click="choose(item)"
                            >
                                <span
                                    class="ps-search__result-icon"
                                    x-show="settings.ui.show_icons !== false && item.icon"
                                >
                                    <i
                                        x-show="isIconClass(item.icon)"
                                        :class="item.icon"
                                    ></i>
                                    <span
                                        x-show="!isIconClass(item.icon)"
                                        x-text="item.icon"
                                    ></span>
                                </span>

                                <span class="ps-search__result-content">
                                    <div
                                        class="ps-search__result-title"
                                        x-html="item.title"
                                    ></div>

                                    <div
                                        class="ps-search__result-description"
                                        x-show="settings.ui.show_descriptions !== false && item.description"
                                        x-html="item.description"
                                    ></div>
                                </span>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="ps-search__footer">
                    <span class="ps-search__footer-group">
                        <span class="ps-search__hint">
                            <kbd class="ps-search__kbd">↑</kbd>
                            <kbd class="ps-search__kbd">↓</kbd>
                            Navegar
                        </span>
                        <span class="ps-search__hint">
                            <kbd class="ps-search__kbd">Enter</kbd>
                            <span x-text="step === 'search' ? 'Abrir' : 'Seleccionar'"></span>
                        </span>
                        <span class="ps-search__hint" x-show="step === 'search'">
                            <kbd class="ps-search__kbd">&gt;</kbd>
                            Comandos
                        </span>
                        <span class="ps-search__hint">
                            <kbd class="ps-search__kbd">ESC</kbd>
                            Cerrar
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </template>
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('puzzleGlobalSearch', (settings, wire, commands) => ({
    settings,
    wire,
    commands: commands || [],
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
                || 'Buscar...';
        }

        if (this.step === 'commands') {
            return 'Buscar comando...';
        }

        return this.settings.ui.placeholder || 'Buscar...';
    },

    get emptyMessage() {
        if (this.step === 'commands') {
            return 'No se encontraron comandos';
        }

        const min = Number(
            this.settings.search.min_characters ?? 2
        );

        return this.query.trim().length < min
            ? 'Escribe para buscar'
            : 'No se encontraron resultados';
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
            category: 'Comandos',
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

    async choose(item) {
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
</script>
@endonce
</div>
