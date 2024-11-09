<template>
    <div class="p-6 bg-white">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Categories</h1>
            <button 
                @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-xl"
            >
                Add New Category
            </button>
        </div>

        <!-- Category Type Tabs -->
        <div class="mb-6 border-b border-gray-200">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button 
                    @click="activeTab = 'location'"
                    :class="['pb-4 px-1 border-b-2 font-medium text-xl', 
                        activeTab === 'location' 
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                >
                    Location Based
                </button>
                <button 
                    @click="activeTab = 'remote'"
                    :class="['pb-4 px-1 border-b-2 font-medium text-xl', 
                        activeTab === 'remote' 
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                >
                    Remote
                </button>
            </nav>
        </div>

        <!-- Categories Table -->
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="w-16 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="w-48 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="w-48 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="w-96 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="w-32 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                    <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                    <th class="w-24 px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="category in filteredCategories" :key="category.id">
                    <td class="px-6 py-4 whitespace-nowrap text-xl">{{ category.id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input 
                            type="file"
                            :ref="el => fileInputs[category.id] = el"
                            class="hidden"
                            accept="image/*"
                            @change="(e) => updateCategoryImage(category, e)"
                        >
                        <div 
                            @click="triggerFileInput(category.id)"
                            class="cursor-pointer hover:opacity-75 transition-opacity"
                        >
                            <img 
                                :src="`${imageUrl}${category.thumbImagePath}`"
                                :alt="category.name"
                                class="h-44 w-full rounded object-cover"
                            >
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <input 
                            v-model="category.name"
                            @focus="storeOriginalValue($event)"
                            @blur="checkAndUpdateField(category, 'name', $event)"
                            @keyup.enter="$event.target.blur()"
                            class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                        >
                    </td>
                    <td class="px-6 py-4">
                        <textarea 
                            v-model="category.description"
                            @focus="storeOriginalValue($event)"
                            @blur="checkAndUpdateField(category, 'description', $event)"
                            @keyup.enter="$event.target.blur()"
                            class="px-2 py-1 w-full h-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                            rows="5"
                        ></textarea>
                    </td>
                    <td class="px-6 py-4">
                        <input 
                            v-model="category.credit"
                            @focus="storeOriginalValue($event)"
                            @blur="checkAndUpdateField(category, 'credit', $event)"
                            @keyup.enter="$event.target.blur()"
                            class="px-2 py-1 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                        >
                    </td>
                    <td class="px-6 py-4">
                        <input 
                            v-model.number="category.rank"
                            type="number"
                            @focus="storeOriginalValue($event)"
                            @blur="checkAndUpdateField(category, 'rank', $event)"
                            @keyup.enter="$event.target.blur()"
                            class="px-2 py-1 w-20 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none text-xl"
                        >
                    </td>
                    <td class="px-6 py-4">
                        <button 
                            @click="deleteCategory(category)"
                            class="text-red-600 hover:text-red-900 text-xl"
                        >
                            Delete
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-xl font-bold mb-4">Add New Category</h3>
                <form @submit.prevent="createCategory" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select 
                            v-model="newCategory.remote"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option :value="false">Location Based</option>
                            <option :value="true">Remote</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input 
                            v-model="newCategory.name"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea 
                            v-model="newCategory.description"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Credit</label>
                        <input 
                            v-model="newCategory.credit"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rank</label>
                        <input 
                            v-model.number="newCategory.rank"
                            type="number"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Image</label>
                        <div class="mt-1 flex items-center">
                            <div v-if="previewImage" class="relative">
                                <img 
                                    :src="previewImage" 
                                    class="h-48 w-64 object-cover rounded-lg"
                                >
                                <button 
                                    @click="clearImage"
                                    class="absolute top-1 right-1 bg-white rounded-full p-1 hover:bg-gray-100"
                                >
                                    <span class="text-gray-500">×</span>
                                </button>
                            </div>
                            <input 
                                type="file"
                                ref="fileInput"
                                class="hidden"
                                accept="image/*"
                                @change="handleImageChange"
                            >
                            <button 
                                v-if="!previewImage"
                                type="button"
                                @click="$refs.fileInput.click()"
                                class="h-48 w-64 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center hover:border-gray-400"
                            >
                                <span class="text-gray-500">Add Image</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button 
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                        >
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const imageUrl = import.meta.env.VITE_IMAGE_URL
const categories = ref([])
const activeTab = ref('location')
const showCreateModal = ref(false)
const newCategory = ref({
    name: '',
    description: '',
    credit: '',
    rank: 0,
    remote: false,
    type: 'c',
    slug: ''
})

const fileInput = ref(null)
const previewImage = ref(null)
const selectedFile = ref(null)

const fileInputs = ref({})

const triggerFileInput = (categoryId) => {
    if (fileInputs.value[categoryId]) {
        fileInputs.value[categoryId].click()
    }
}

const storeOriginalValue = (event) => {
    console.log('Storing original value:', event.target.value)
    event.target.setAttribute('data-original', event.target.value)
}

const filteredCategories = computed(() => {
    return categories.value.filter(cat => 
        activeTab.value === 'remote' ? cat.remote === true : cat.remote === false
    )
})

const fetchCategories = async () => {
    try {
        const response = await axios.get('/api/admin/settings/categories')
        categories.value = response.data
    } catch (error) {
        console.error('Error fetching categories:', error)
    }
}

const checkAndUpdateField = async (category, field, event) => {
    const originalValue = event.target.getAttribute('data-original')
    const newValue = event.target.value
    
    if (originalValue !== newValue) {
        try {
            if (confirm(`Are you sure you want to update this category's ${field}?`)) {
                const formData = new FormData()
                formData.append(field, newValue)
                formData.append('name', category.name)
                formData.append('description', category.description)
                formData.append('credit', category.credit || '')
                formData.append('rank', category.rank || 0)
                formData.append('remote', category.remote ? 1 : 0)
                formData.append('type', category.type || 'c')

                const response = await axios.post(
                    `/api/admin/settings/categories/${category.slug}`,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )
                Object.assign(category, response.data)
                event.target.setAttribute('data-original', newValue)
            } else {
                category[field] = originalValue
                event.target.value = originalValue
            }
        } catch (error) {
            console.error('Update error:', error)
            category[field] = originalValue
            event.target.value = originalValue
            alert(error.response?.data?.message || `Error updating ${field}`)
        }
    }
}

const handleImageChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        previewImage.value = URL.createObjectURL(file)
    }
}

const clearImage = () => {
    previewImage.value = null
    selectedFile.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const createCategory = async () => {
    try {
        const formData = new FormData()
        formData.append('name', newCategory.value.name)
        formData.append('description', newCategory.value.description || '')
        formData.append('credit', newCategory.value.credit || '')
        formData.append('rank', newCategory.value.rank || 0)
        formData.append('remote', newCategory.value.remote ? 1 : 0)
        formData.append('type', newCategory.value.type || 'c')
        formData.append('slug', newCategory.value.name.toLowerCase().replace(/\s+/g, '-'))
        
        if (selectedFile.value) {
            formData.append('image', selectedFile.value)
        }

        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1])
        }

        await axios.post('/api/admin/settings/categories', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        await fetchCategories()
        showCreateModal.value = false
        clearImage()
        newCategory.value = {
            name: '',
            description: '',
            credit: '',
            rank: 0,
            remote: false,
            type: 'c',
            slug: ''
        }
    } catch (error) {
        console.error('Error creating category:', error)
        alert(error.response?.data?.message || 'Error creating category')
    }
}

const deleteCategory = async (category) => {
    if (!confirm('Are you sure you want to delete this category?')) return
    
    try {
        await axios.delete(`/api/admin/settings/categories/${category.slug}`)
        await fetchCategories()
    } catch (error) {
        console.error('Error deleting category:', error)
        alert(error.response?.data?.message || 'Error deleting category')
    }
}

const updateCategoryImage = async (category, event) => {
    const file = event.target.files[0]
    if (!file) return

    try {
        const formData = new FormData()
        formData.append('image', file)
        formData.append('name', category.name)
        formData.append('description', category.description)
        formData.append('credit', category.credit || '')
        formData.append('rank', category.rank || 0)
        formData.append('remote', category.remote ? 1 : 0)
        formData.append('type', category.type || 'c')

        const response = await axios.post(
            `/api/admin/settings/categories/${category.slug}`, 
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        )
        Object.assign(category, response.data)
    } catch (error) {
        console.error('Error updating image:', error)
        alert(error.response?.data?.message || 'Error updating image')
    }
}

const getCategoryImage = (category) => {
    return `${imageUrl}${category.thumbImagePath}`
}

onMounted(() => {
    fetchCategories()
})
</script>

<style scoped>
input, textarea {
    background: transparent;
    width: 100%;
}

input:hover, textarea:hover {
    background: #f8f8f8;
}

input:focus, textarea:focus {
    background: white;
}
</style>
