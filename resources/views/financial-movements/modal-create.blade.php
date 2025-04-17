<div x-data="{
    selectedType: 'income',
    isLoading: false,
    notification: '',
    errorMessages: [],
    submitForm() {
        this.isLoading = true;
        this.notification = '';
        this.errorMessages = [];
        let form = this.$refs.financialForm;

        if (!form) {
            console.error('Form reference not found.');
            this.isLoading = false;
            return;
        }

        let formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.$dispatch('close-modal', 'modal-create');
                this.notification = 'Financial movement saved successfully!';
            } else if (data.errors) {
                this.errorMessages = Object.values(data.errors).flat();
            } else {
                this.notification = 'Error saving! Please check the fields and try again.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.notification = 'Unexpected error. Please try again later.';
        })
        .finally(() => this.isLoading = false);
    }
}">

    <x-modal name="modal-create" :show="false" focusable>
        <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg p-6 space-y-6">
            <h2 class="text-lg font-bold text-gray-900">{{ __('Create') }}</h2>

            <template x-if="errorMessages.length">
                <div class="bg-red-500 text-white p-4 rounded shadow-lg">
                    <ul>
                        <template x-for="error in errorMessages" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
            </template>

            <form x-ref="financialForm" @submit.prevent="submitForm" method="POST" action="{{ route('financial-movements.store') }}" enctype="multipart/form-data">
                @csrf
                @include('financial-movements.form')
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded mt-4" :disabled="isLoading">
                    <span x-show="!isLoading">Save</span>
                    <span x-show="isLoading">Sending...</span>
                </button>
            </form>
        </div>
    </x-modal>

    <template x-if="notification">
        <div class="fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg" x-text="notification" x-show="notification" x-transition></div>
    </template>

    <div class="flex fixed bottom-4 z-50 right-4 gap-4">
        <button class="bg-green-500 rounded-full text-white w-12 h-12 shadow-lg"
            @click.prevent="selectedType = 'income'; $dispatch('open-modal', 'modal-create')">
            <span class="fa fa-plus"></span>
        </button>

        <button class="bg-red-500 rounded-full text-white w-12 h-12 shadow-lg"
            @click.prevent="selectedType = 'expense'; $dispatch('open-modal', 'modal-create')">
            <span class="fa fa-minus"></span>
        </button>
    </div>
</div>
