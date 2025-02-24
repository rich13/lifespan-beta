@props([
    'name',
    'value' => null,
    'label' => null,
    'required' => false,
    'help' => null,
    'spanType' => null, // Optional restriction on span type
])

<div class="span-input" x-data="{
    spanId: @json($value),
    spanName: '',
    searchResults: [],
    isSearching: false,
    isCreating: false,
    searchQuery: '',
    
    async search() {
        if (!this.searchQuery.trim()) {
            this.searchResults = [];
            return;
        }
        
        this.isSearching = true;
        try {
            const response = await fetch(`/api/spans/search?q=${encodeURIComponent(this.searchQuery)}${this.spanType ? '&type=' + this.spanType : ''}`);
            const data = await response.json();
            this.searchResults = data;
        } catch (error) {
            console.error('Search failed:', error);
            this.searchResults = [];
        }
        this.isSearching = false;
    },

    async createPlaceholder() {
        if (!this.spanName.trim()) return;
        
        try {
            const response = await fetch('/api/spans', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    name: this.spanName,
                    type_id: this.spanType || 'placeholder',
                    state: 'placeholder'
                })
            });
            
            const data = await response.json();
            if (data.id) {
                this.spanId = data.id;
                this.spanName = data.name;
                this.isCreating = false;
            }
        } catch (error) {
            console.error('Failed to create placeholder:', error);
        }
    }
}">
    <input type="hidden" :name="name" :value="spanId">
    
    <!-- Selected Span Display -->
    <template x-if="spanId">
        <div class="selected-span mb-2">
            <div class="d-flex align-items-center">
                <span x-text="spanName" class="me-2"></span>
                <button type="button" class="btn btn-sm btn-outline-danger" 
                        @click="spanId = null; spanName = ''">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    </template>

    <!-- Search/Create Interface -->
    <template x-if="!spanId">
        <div>
            <!-- Search Input -->
            <div class="input-group mb-2">
                <input type="text" class="form-control" 
                       placeholder="Search for existing span..."
                       x-model="searchQuery"
                       @input.debounce.300ms="search()">
                <button type="button" class="btn btn-outline-secondary"
                        @click="isCreating = true">
                    <i class="bi bi-plus-lg"></i> New
                </button>
            </div>

            <!-- Search Results -->
            <div class="search-results mb-2" x-show="searchResults.length > 0">
                <div class="list-group">
                    <template x-for="result in searchResults" :key="result.id">
                        <button type="button" 
                                class="list-group-item list-group-item-action"
                                @click="spanId = result.id; spanName = result.name; searchResults = []">
                            <div x-text="result.name"></div>
                            <small class="text-muted" x-text="result.type_name"></small>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Create New Placeholder -->
            <div x-show="isCreating" class="create-placeholder">
                <div class="input-group">
                    <input type="text" class="form-control"
                           placeholder="Enter name for new span..."
                           x-model="spanName">
                    <button type="button" class="btn btn-primary"
                            @click="createPlaceholder()">
                        Create
                    </button>
                    <button type="button" class="btn btn-outline-secondary"
                            @click="isCreating = false">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </template>

    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div> 