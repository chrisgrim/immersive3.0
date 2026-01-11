<template>
  <div class="categories-component">
    <div class="h-[calc(100vh-12rem)] flex flex-col md:h-[calc(100vh-12rem)] max-h-[calc(100vh-10rem)]">
        <!-- Fixed Header Section -->
        <div class="flex-none">
            <h1 class="text-2xl font-bold mb-6">Categories Management</h1>
            
            <!-- Add Button Section -->
            <div class="flex justify-end mb-6">
                <button 
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                >
                    Add New Category
                </button>
            </div>
        </div>

        <!-- Scrollable Table Section -->
        <div class="flex-1 overflow-auto border border-neutral-200 rounded-xl">
            <table class="w-full">
                <thead class="sticky top-0 bg-white shadow-sm">
                    <tr class="bg-neutral-100">
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Main Image</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Icon</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Name</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">Description</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Attendance Types</th>
                        <th class="px-6 py-3 text-left text-xl font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="category in categories" :key="category.id">
                        <td class="px-6 py-4 whitespace-nowrap text-xl">{{ category.id }}</td>
                        <td class="px-6 py-4 min-w-[100px]">
                            <input 
                                type="file"
                                :ref="el => fileInputs[`main_${category.id}`] = el"
                                class="hidden"
                                accept="image/jpeg,image/png,image/webp"
                                @change="(e) => updateCategoryImage(category, e, 0)"
                            >
                            <div 
                                @click="triggerFileInput(`main_${category.id}`)"
                                class="cursor-pointer hover:opacity-75 transition-opacity"
                            >
                                <img 
                                    v-if="category.images?.find(img => img.rank === 0)"
                                    :src="`${imageUrl}${category.images.find(img => img.rank === 0).thumb_image_path}`"
                                    :alt="category.name"
                                    class="h-24 w-32 rounded object-cover"
                                    @error="handleImageError($event)"
                                >
                                <img 
                                    v-else-if="category.thumbImagePath"
                                    :src="`${imageUrl}${category.thumbImagePath}`"
                                    :alt="category.name"
                                    class="h-24 w-32 rounded object-cover"
                                    @error="handleImageError($event)"
                                >
                                <div v-else class="h-24 w-32 rounded bg-gray-200 flex items-center justify-center">
                                    <span class="text-gray-400">Add Image</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 min-w-[100px]">
                            <input 
                                type="file"
                                :ref="el => fileInputs[`icon_${category.id}`] = el"
                                class="hidden"
                                accept="image/jpeg,image/png,image/webp"
                                @change="(e) => updateCategoryImage(category, e, 1)"
                            >
                            <div 
                                @click="triggerFileInput(`icon_${category.id}`)"
                                class="cursor-pointer hover:opacity-75 transition-opacity"
                            >
                                <img 
                                    v-if="category.images?.find(img => img.rank === 1)"
                                    :src="`${imageUrl}${category.images.find(img => img.rank === 1).thumb_image_path}`"
                                    :alt="`${category.name} icon`"
                                    class="h-32 w-32 rounded object-cover"
                                    @error="handleImageError($event)"
                                >
                                <div v-else class="h-32 w-32 rounded bg-gray-200 flex items-center justify-center">
                                    <span class="text-gray-400">Add Icon</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 min-w-[100px]">
                            <input 
                                v-model="category.name"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(category, 'name', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 w-full border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none text-xl"
                            >
                        </td>
                        <td class="px-6 py-4 min-w-[200px]">
                            <textarea 
                                v-model="category.description"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(category, 'description', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 w-full border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none text-xl"
                                rows="5"
                            ></textarea>
                        </td>
                        <td class="px-6 py-4">
                            <input 
                                v-model="category.credit"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(category, 'credit', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 w-full border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none text-xl"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <input 
                                v-model.number="category.rank"
                                type="number"
                                @focus="storeOriginalValue($event)"
                                @blur="checkAndUpdateField(category, 'rank', $event)"
                                @keyup.enter="$event.target.blur()"
                                class="px-2 py-1 w-20 border-b border-transparent hover:bg-ne focus:bg-white focus:border-blue-500 focus:outline-none text-xl"
                            >
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <button 
                                    @click="openAttendanceTypeModal(category)"
                                    class="text-blue-600 hover:text-blue-900 text-xl">
                                    {{ formatApplicableTypes(category) }}
                                </button>
                            </div>
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
        </div>
    </div>

    <teleport to="body">
        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
            <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Add New Category</h2>
                    <p class="text-gray-500 font-normal">Create a new category for events</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <div class="relative">
                            <p class="text-gray-500 font-normal mb-4">Applicable Attendance Types</p>
                            <div class="flex flex-wrap gap-2">
                                <div 
                                    v-for="type in attendanceTypes" 
                                    :key="type.id"
                                    class="flex items-center space-x-2"
                                >
                                    <input 
                                        type="checkbox" 
                                        :id="`type-${type.id}`" 
                                        v-model="newCategory.applicable_attendance_types"
                                        :value="type.id"
                                        class="rounded border-gray-300 text-blue-600 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                    >
                                    <label :for="`type-${type.id}`" class="text-lg">{{ type.name }}</label>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">If none selected, applies to all attendance types</p>
                        </div>

                        <div>
                            <p class="text-gray-500 font-normal mb-4">Name</p>
                            <input 
                                v-model="newCategory.name"
                                type="text"
                                required
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                placeholder="Enter category name"
                            >
                        </div>

                        <div>
                            <p class="text-gray-500 font-normal mb-4">Description</p>
                            <textarea 
                                v-model="newCategory.description"
                                required
                                rows="3"
                                class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                placeholder="Enter category description"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-gray-500 font-normal mb-4">Credit</p>
                                <input 
                                    v-model="newCategory.credit"
                                    type="text"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4"
                                    placeholder="Enter credits"
                                >
                            </div>

                            <div>
                                <p class="text-gray-500 font-normal mb-4">Rank</p>
                                <select 
                                    v-model.number="newCategory.rank"
                                    class="w-full text-xl border border-neutral-400 focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 bg-white"
                                >
                                    <option v-for="n in 6" :key="n-1" :value="n-1">{{ n-1 }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <!-- Main Image -->
                            <div class="flex flex-col items-center">
                                <p class="text-gray-500 font-normal mb-4">Main Image</p>
                                <input 
                                    type="file"
                                    ref="mainImageInput"
                                    class="hidden"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="(e) => handleImageChange(e, 0)"
                                >
                                <div 
                                    @click="$refs.mainImageInput.click()"
                                    class="cursor-pointer hover:opacity-75 transition-opacity"
                                >
                                    <img 
                                        v-if="previewImages[0]"
                                        :src="previewImages[0]"
                                        class="h-32 w-32 rounded-2xl object-cover"
                                    >
                                    <div 
                                        v-else
                                        class="h-32 w-32 rounded-2xl border border-neutral-400 flex flex-col items-center justify-center gap-2"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-gray-400 text-lg text-center">Add Main Image</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Icon Image -->
                            <div class="flex flex-col items-center">
                                <p class="text-gray-500 font-normal mb-4">Icon</p>
                                <input 
                                    type="file"
                                    ref="iconImageInput"
                                    class="hidden"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="(e) => handleImageChange(e, 1)"
                                >
                                <div 
                                    @click="$refs.iconImageInput.click()"
                                    class="cursor-pointer hover:opacity-75 transition-opacity"
                                >
                                    <img 
                                        v-if="previewImages[1]"
                                        :src="previewImages[1]"
                                        class="h-32 w-32 rounded-2xl object-cover"
                                    >
                                    <div 
                                        v-else
                                        class="h-32 w-32 rounded-2xl border border-neutral-400 flex flex-col items-center justify-center gap-2"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-gray-400 text-lg text-center">Add Icon</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>       
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="showCreateModal = false"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-ne text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="createCategory"
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Create
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>

    <teleport to="body">
        <!-- Attendance Types Modal -->
        <div v-if="showAttendanceTypeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white w-full max-w-2xl md:mx-4 md:rounded-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                <!-- Header -->
                <div class="p-8 pb-6">
                    <h2 class="text-2xl font-bold mb-2">Edit Attendance Types</h2>
                    <p class="text-gray-500 font-normal">Manage applicable attendance types for this category</p>
                </div>

                <!-- Scrollable Content -->
                <div class="p-8 overflow-y-auto flex-1">
                    <div class="space-y-6">
                        <div class="relative">
                            <p class="text-gray-500 font-normal mb-4">Applicable Attendance Types</p>
                            <div class="flex flex-wrap gap-2">
                                <div 
                                    v-for="type in attendanceTypes" 
                                    :key="type.id"
                                    class="flex items-center space-x-2"
                                >
                                    <input 
                                        type="checkbox" 
                                        :id="`edit-type-${type.id}`" 
                                        v-model="editingCategory.applicable_attendance_types"
                                        :value="type.id"
                                        class="rounded border-gray-300 text-blue-600 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                    >
                                    <label :for="`edit-type-${type.id}`" class="text-lg">{{ type.name }}</label>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">If none selected, applies to all attendance types</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                    <div class="flex justify-end space-x-4">
                        <button 
                            @click="showAttendanceTypeModal = false"
                            class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-ne text-xl"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="saveAttendanceTypes"
                            class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'

// Define props with all expected props, even if not used
const props = defineProps({
  event: {
    type: Object,
    default: null
  },
  organizer: {
    type: Object,
    default: null
  },
  community: {
    type: Object,
    default: null
  }
})

// Define all emits, including those passed by parent components
const emit = defineEmits([
  'selectEvent',
  'selectOrganizer',
  'selectCommunity',
  'approved',
  'rejected',
  'organizerApproved',
  'organizerRejected',
  'communityApproved',
  'communityRejected',
  'updateCounts'
])

const imageUrl = import.meta.env.VITE_IMAGE_URL
const categories = ref([])
const showCreateModal = ref(false)
const newCategory = ref({
    name: '',
    description: '',
    credit: '',
    rank: 0,
    type: 'c',
    slug: '',
    applicable_attendance_types: []
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
                // Only send the changed field rather than all fields
                const data = { [field]: newValue }
                
                const response = await axios.patch(
                    `/api/admin/settings/categories/${category.slug}`,
                    data
                )
                
                // Update the local category with the response data
                Object.assign(category, response.data)
                event.target.setAttribute('data-original', newValue)
            } else {
                // User canceled, revert the field
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

const previewImages = ref([])
const selectedFiles = ref([])

const handleImageChange = (event, imageIndex) => {
    const file = event.target.files[0]
    if (file) {
        while (selectedFiles.value.length <= imageIndex) {
            selectedFiles.value.push(null)
            previewImages.value.push(null)
        }
        selectedFiles.value[imageIndex] = file
        previewImages.value[imageIndex] = URL.createObjectURL(file)
    }
}

const mainImageInput = ref(null)
const iconImageInput = ref(null)

const createCategory = async () => {
    try {
        // Validate image sizes before sending
        const maxSize = 2048 * 1024; // 2048 KB in bytes
        if (selectedFiles.value[0] && selectedFiles.value[0].size > maxSize) {
            throw new Error('Main image must not be larger than 2MB')
        }
        if (selectedFiles.value[1] && selectedFiles.value[1].size > maxSize) {
            throw new Error('Icon image must not be larger than 2MB')
        }

        const formData = new FormData()
        formData.append('name', newCategory.value.name)
        formData.append('description', newCategory.value.description || '')
        formData.append('credit', newCategory.value.credit || '')
        formData.append('rank', newCategory.value.rank || 0)
        formData.append('type', newCategory.value.type || 'c')
        formData.append('slug', newCategory.value.name.toLowerCase().replace(/\s+/g, '-'))
        
        // Add applicable attendance types if any are selected - as proper array for store method
        if (newCategory.value.applicable_attendance_types && newCategory.value.applicable_attendance_types.length > 0) {
            // For FormData, we need to append each array item with the same key
            newCategory.value.applicable_attendance_types.forEach(typeId => {
                formData.append('applicable_attendance_types[]', typeId);
            });
        }
        
        // Append both images if they exist
        if (selectedFiles.value[0]) {
            formData.append('image[]', selectedFiles.value[0])
            formData.append('image_index[]', 0)
        }
        if (selectedFiles.value[1]) {
            formData.append('image[]', selectedFiles.value[1])
            formData.append('image_index[]', 1)
        }

        const response = await axios.post('/api/admin/settings/categories', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })

        // Only proceed if we get a successful response
        if (response.data) {
            await fetchCategories()
            showCreateModal.value = false
            resetForm()
        }
    } catch (error) {
        console.error('Error creating category:', error)
        
        // Handle validation errors
        if (error.response?.status === 422) {
            const errorMessage = error.response.data.message || 
                              Object.values(error.response.data.errors || {})[0]?.[0] ||
                              'Validation error occurred'
            alert(errorMessage)
        } else if (error.message) {
            // Handle client-side validation errors
            alert(error.message)
        } else {
            alert('Error creating category')
        }
        
        // Don't refresh categories if it's just a validation error
        if (error.response?.status !== 422) {
            await fetchCategories()
        }
    }
}

// Separate reset form function
const resetForm = () => {
    newCategory.value = {
        name: '',
        description: '',
        credit: '',
        rank: 0,
        type: 'c',
        slug: '',
        applicable_attendance_types: []
    }
    clearImage()
}

// Update clearImage function
const clearImage = () => {
    previewImages.value = []
    selectedFiles.value = []
    // Safely clear file inputs if they exist
    if (mainImageInput.value) mainImageInput.value.value = ''
    if (iconImageInput.value) iconImageInput.value.value = ''
}

const deleteCategory = async (category) => {
    if (!confirm('Are you sure you want to delete this category?')) return
    
    try {
        await axios.delete(`/api/admin/settings/categories/${category.slug}`)
        await fetchCategories()
    } catch (error) {
        console.error('Error deleting category:', error)
        if (error.response?.data?.error === 'CATEGORY_HAS_EVENTS') {
            alert('This category cannot be deleted because it has events associated with it. Please remove all events from this category first.')
        } else {
            alert(error.response?.data?.message || 'Error deleting category')
        }
    }
}

const updateCategoryImage = async (category, event, imageIndex) => {
    const file = event.target.files[0];
    if (!file) return;

    try {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('image_index', imageIndex);
        
        const response = await axios.post(
            `/api/admin/settings/categories/${category.slug}`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );

        // Initialize images array if it doesn't exist
        if (!category.images) {
            category.images = [];
        }

        // Update or add the image at the specified rank
        const existingImageIndex = category.images.findIndex(img => img.rank === imageIndex);
        if (existingImageIndex !== -1) {
            category.images[existingImageIndex] = response.data.images.find(img => img.rank === imageIndex);
        } else {
            category.images.push(response.data.images.find(img => img.rank === imageIndex));
        }

        // If this is a main image (rank 0), also update the legacy fields
        if (imageIndex === 0) {
            category.thumbImagePath = response.data.thumbImagePath;
            category.largeImagePath = response.data.largeImagePath;
        }
    } catch (error) {
        console.error('Error updating image:', error);
        alert(error.response?.data?.message || 'Error updating image');
    }
};

const getCategoryImage = (category) => {
    return `${imageUrl}${category.thumbImagePath}`
}

const handleImageError = (event) => {
    console.error('Image failed to load:', event.target.src);
    event.target.src = 'https://placehold.co/32x24?text=Error';
};

// Add attendance types data
const attendanceTypes = ref([])

// Get all attendance types after onMounted
const fetchAttendanceTypes = async () => {
    try {
        const response = await axios.get('/api/admin/settings/attendance-types')
        attendanceTypes.value = response.data
    } catch (error) {
        console.error('Error fetching attendance types:', error)
    }
}

// Add methods and reactive variables needed for the attendance types modal
const editingCategory = ref(null)
const showAttendanceTypeModal = ref(false)

const openAttendanceTypeModal = (category) => {
    editingCategory.value = JSON.parse(JSON.stringify(category)) // Deep clone to prevent direct reference
    
    // Ensure attendance types is an array
    if (!editingCategory.value.applicable_attendance_types) {
        editingCategory.value.applicable_attendance_types = []
    }
    
    showAttendanceTypeModal.value = true
}

const saveAttendanceTypes = async () => {
    try {
        // Find the original category
        const categoryIndex = categories.value.findIndex(cat => cat.id === editingCategory.value.id)
        if (categoryIndex === -1) return
        
        const originalCategory = categories.value[categoryIndex]
        
        // Make sure we have an array, even if empty
        const attendanceTypes = editingCategory.value.applicable_attendance_types || []
        
        // Check if there are changes
        const originalTypes = originalCategory.applicable_attendance_types || []
        const hasChanges = JSON.stringify(originalTypes) !== JSON.stringify(attendanceTypes)
        
        if (hasChanges) {
            const response = await axios.patch(
                `/api/admin/settings/categories/${editingCategory.value.slug}`,
                {
                    applicable_attendance_types: JSON.stringify(attendanceTypes)
                }
            )
            
            // Update local data
            Object.assign(originalCategory, response.data)
        }
        
        // Close modal in all cases
        showAttendanceTypeModal.value = false
        editingCategory.value = null
    } catch (error) {
        console.error('Error updating attendance types:', error)
        alert(error.response?.data?.message || 'Error updating attendance types')
    }
}

const formatApplicableTypes = (category) => {
    if (!category.applicable_attendance_types || !category.applicable_attendance_types.length) {
        return 'All types'
    }
    
    return attendanceTypes.value
        .filter(type => category.applicable_attendance_types.includes(type.id))
        .map(type => type.name)
        .join(', ')
}

onMounted(() => {
    fetchCategories()
    fetchAttendanceTypes()
})
</script>

<style scoped>
thead {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>
