<div
    x-data="puzzleGlobalSearch(@js($settings), $wire, @js($commands), @js($labels))"
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
                aria-label="{{ $labels['dialog'] }}"
            >
                <div class="ps-search__header">
                    <button
                        x-show="step === 'dependency'"
                        type="button"
                        class="ps-search__back"
                        @click="back()"
                        aria-label="{{ $labels['back'] }}"
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
                        aria-label="{{ $labels['search'] }}"
                    >

                    <span
                        class="ps-search__spinner"
                        x-show="loading"
                        x-cloak
                        role="status"
                        aria-label="{{ $labels['searching'] }}"
                    ></span>

                    <button
                        type="button"
                        class="ps-search__kbd"
                        @click="close()"
                        aria-label="{{ $labels['close'] }}"
                    >ESC</button>
                </div>

                <div class="ps-search__results">
                    <template x-if="loading && items.length === 0">
                        <div class="ps-search__message">
                            {{ $labels['searching_message'] }}
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
                            {{ $labels['navigate'] }}
                        </span>
                        <span class="ps-search__hint">
                            <kbd class="ps-search__kbd">Enter</kbd>
                            <span x-text="step === 'search' ? labels.open : labels.select"></span>
                        </span>
                        <span class="ps-search__hint" x-show="step === 'search'">
                            <kbd class="ps-search__kbd">&gt;</kbd>
                            {{ $labels['commands'] }}
                        </span>
                        <span class="ps-search__hint">
                            <kbd class="ps-search__kbd">ESC</kbd>
                            {{ $labels['close'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </template>
@once
    <script>
        {!! file_get_contents($globalSearchJsPath) !!}
    </script>
@endonce
</div>
