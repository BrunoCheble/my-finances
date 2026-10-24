<script>
    /**
     * Movement form (create and edit): type, amount with comma decimals, history
     * suggestion by description, searchable category with inline creation.
     */
    function movementForm(config) {
        const iso = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

        return {
            ...config,
            moreTypes: false,
            suggestion: '',
            typeTouched: config.mode === 'edit',
            categoryTouched: config.mode === 'edit',
            categoriesOpen: false,
            categorySearch: '',
            highlight: 0,
            categorySaving: false,
            categoryError: null,
            descriptionsOpen: false,
            descriptionSuggestions: [],
            descriptionHighlight: -1,
            descriptionTimer: null,
            descriptionRequest: 0,

            init() {
                // after a successful create, keep date, wallet and type for the next entry
                window.addEventListener('movement-form-reset', () => {
                    if (this.mode !== 'create') return;
                    this.amount = '';
                    this.description = '';
                    this.categoryId = '';
                    this.includeAlert = false;
                    this.loanPerson = '';
                    this.suggestion = '';
                    this.typeTouched = this.categoryTouched = false;
                    this.$nextTick(() => this.$refs.amount.focus());
                });
            },

            get mainTypes() {
                return [
                    { value: 'expense', icon: 'arrow-down', label: this.types.expense, active: 'text-rose-600' },
                    { value: 'income', icon: 'arrow-up', label: this.types.income, active: 'text-emerald-600' },
                    { value: 'transfer', icon: 'arrow-right-arrow-left', label: this.types.transfer, active: 'text-brand-700' },
                ];
            },
            get otherTypes() {
                return [
                    { value: 'refund', icon: 'rotate-left', label: this.types.refund },
                    { value: 'discount', icon: 'percent', label: this.types.discount },
                    { value: 'loan', icon: 'handshake', label: this.types.loan },
                    { value: 'adjustment', icon: 'sliders', label: this.types.adjustment },
                ];
            },
            get isOtherType() {
                return !['expense', 'income', 'transfer'].includes(this.type);
            },
            // the latest confirmed month this save would change, as a sentence ('' when none)
            get reconciledWarning() {
                const positions = [{ walletId: this.walletId, date: this.date }];
                if (this.type === 'transfer') positions.push({ walletId: this.transferToWalletId, date: this.date });
                positions.push(...this.originalPositions);

                for (const position of positions) {
                    const last = this.reconciled[position.walletId];
                    if (!last || !position.date || position.date.slice(0, 7) > last) continue;
                    const month = new Date(last + '-01T00:00:00').toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });
                    return @json(__('Changes the confirmed balance of :wallet in :month.'))
                        .replace(':wallet', this.allWallets[position.walletId] || '')
                        .replace(':month', month);
                }
                return '';
            },
            setType(value) {
                this.type = value;
                this.typeTouched = true;
            },

            // amount: accepts "1.234,56", "45,9" or "45.90"
            get normalizedAmount() {
                const text = String(this.amount).trim();
                return text.includes(',') ? text.replace(/\./g, '').replace(',', '.') : text;
            },
            get openLoan() {
                const name = String(this.loanPerson).trim().toLowerCase();
                return this.openLoans.find(l => l.person.toLowerCase() === name) || null;
            },
            money(value) {
                return Number(value).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
            },
            get amountSign() {
                if (this.type === 'loan') return this.loanDirection === 'lend' ? '−' : '+';
                if (['expense', 'discount'].includes(this.type)) return '−';
                if (['income', 'refund'].includes(this.type)) return '+';
                return '';
            },
            get amountColor() {
                if (this.type === 'loan') return 'text-amber-700';
                if (['expense', 'discount'].includes(this.type)) return 'text-rose-600';
                if (['income', 'refund'].includes(this.type)) return 'text-emerald-600';
                return 'text-slate-900';
            },

            today() { return iso(new Date()); },
            yesterday() { const d = new Date(); d.setDate(d.getDate() - 1); return iso(d); },

            // the last movement with this description suggests type and category
            async lookupHistory() {
                this.suggestion = '';
                if (!this.description || this.mode === 'edit') return;
                const params = new URLSearchParams({ search: this.description });
                const response = await fetch(@json(route('financial-movements.fetchLatestTypeAndCategory')) + '?' + params);
                const last = await response.json();
                if (!last || !last.type) return;

                if (!this.typeTouched) this.type = last.type;
                if (!this.categoryTouched && last.category_id) this.categoryId = String(last.category_id);

                const category = this.categories.find(c => c.id === String(last.category_id));
                this.suggestion = @json(__('Last time:')) + ' ' + [this.types[last.type], category?.name].filter(Boolean).join(' · ');
            },

            // description autocomplete: what was typed before, most used first
            searchDescriptions() {
                clearTimeout(this.descriptionTimer);
                const term = String(this.description).trim();
                if (term.length < 2) return this.closeDescriptions();
                this.descriptionTimer = setTimeout(async () => {
                    const request = ++this.descriptionRequest;
                    const response = await fetch(@json(route('financial-movements.suggestions')) + '?' + new URLSearchParams({ q: term }), { headers: { 'Accept': 'application/json' } });
                    const items = response.ok ? await response.json() : [];
                    if (request !== this.descriptionRequest) return; // a newer search already answered
                    // nothing to suggest when the only match is exactly what was typed
                    const useful = items.filter((item) => item.description.toLowerCase() !== term.toLowerCase() || items.length > 1);
                    this.descriptionSuggestions = useful;
                    this.descriptionHighlight = -1;
                    this.descriptionsOpen = useful.length > 0 && document.activeElement === this.$refs.description;
                }, 180);
            },
            moveDescription(step) {
                if (!this.descriptionsOpen) return this.searchDescriptions();
                const count = this.descriptionSuggestions.length;
                this.descriptionHighlight = (this.descriptionHighlight + step + count) % count;
            },
            pickDescription(i) {
                const item = this.descriptionSuggestions[i];
                if (!item) return;
                this.description = item.description;
                // in a new movement the last type and category come along
                if (this.mode === 'create') {
                    if (item.type) this.type = item.type;
                    if (item.category_id) this.categoryId = item.category_id;
                    this.typeTouched = this.categoryTouched = true;
                    this.suggestion = @json(__('Last time:')) + ' ' + [this.types[item.type], item.category, this.money(item.amount)].filter(Boolean).join(' · ');
                }
                this.closeDescriptions();
            },
            closeDescriptions() {
                clearTimeout(this.descriptionTimer);
                this.descriptionRequest++;
                this.descriptionsOpen = false;
                this.descriptionHighlight = -1;
            },

            // category picker
            get needsCategory() {
                return ['expense', 'income', 'refund', 'discount'].includes(this.type);
            },
            get categoryKind() {
                return ['income', 'discount'].includes(this.type) ? 'income' : 'expense';
            },
            get categoryOptions() {
                const term = this.categorySearch.trim().toLowerCase();
                const matches = this.categories.filter(c => c.name.toLowerCase().includes(term));
                const byUse = (a, b) => b.uses - a.uses || a.name.localeCompare(b.name);
                const toOption = c => ({ key: 'c' + c.id, id: c.id, label: c.name, uses: c.uses });

                const options = matches.filter(c => c.type === this.categoryKind).sort(byUse).map(toOption);
                const others = matches.filter(c => c.type !== this.categoryKind).sort(byUse).map(toOption);
                if (others.length) options.push({ key: 'h', header: @json(__('Other categories')) }, ...others);

                if (term && !this.categories.some(c => c.name.toLowerCase() === term)) {
                    const kind = this.categoryKind === 'income' ? @json(__('as income')) : @json(__('as expense'));
                    options.push({ key: 'new', create: true, label: '+ ' + @json(__('Create')) + ' "' + this.categorySearch.trim() + '" ' + kind });
                }
                return options;
            },
            categoryName() {
                return this.categories.find(c => c.id === this.categoryId)?.name || '';
            },
            firstOption() {
                return Math.max(0, this.categoryOptions.findIndex(o => !o.header));
            },
            openCategories() {
                if (this.categoriesOpen) return;
                this.categorySearch = '';
                this.categoryError = null;
                this.categoriesOpen = true;
                this.highlight = this.firstOption();
            },
            closeCategories() {
                this.categoriesOpen = false;
                this.categorySearch = '';
            },
            moveHighlight(step) {
                if (!this.categoriesOpen) return this.openCategories();
                const options = this.categoryOptions;
                let next = this.highlight;
                do {
                    next = (next + step + options.length) % options.length;
                } while (options[next]?.header && next !== this.highlight);
                this.highlight = next;
            },
            async pickCategory(i) {
                const option = this.categoryOptions[i];
                if (!option || option.header) return;
                if (option.create) return this.createCategory(this.categorySearch.trim());
                this.categoryId = option.id;
                this.categoryTouched = true;
                this.closeCategories();
            },
            async createCategory(name) {
                this.categorySaving = true;
                this.categoryError = null;
                try {
                    const response = await fetch(@json(route('financial-categories.store')), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name, type: this.categoryKind, active: 1 }),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        this.categoryError = Object.values(data.errors || {}).flat()[0] || data.message;
                        return;
                    }
                    this.categories.push({ id: String(data.id), name: data.name, type: data.type, uses: 0 });
                    this.categoryId = String(data.id);
                    this.categoryTouched = true;
                    this.closeCategories();
                } catch (e) {
                    this.categoryError = @json(__('Something went wrong'));
                } finally {
                    this.categorySaving = false;
                }
            },
        };
    }
</script>
