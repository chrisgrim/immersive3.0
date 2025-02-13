<template>
    <main class="w-full min-h-fit">
        <div class="w-full">
            <div class="w-full max-w-[64rem] mx-auto mb-16">
                <h2>Community Curators</h2>
                <p class="text-gray-500 mt-4">
                    {{ canManageCurators ? 'Manage who can curate content for your community' : 'View community curators' }}
                </p>
            </div>

            <div class="space-y-8">
                <!-- Owner Section -->
                <div class="border rounded-2xl p-6">
                    <h3 class="text-xl font-semibold mb-4">Owner</h3>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div v-if="community.owner?.avatar" class="w-12 h-12">
                                <picture>
                                    <source :srcset="community.owner.avatar" type="image/webp">
                                    <img 
                                        :src="community.owner.avatar.replace(/\.webp$/, '.jpg')"
                                        class="w-12 h-12 rounded-full"
                                        alt="Owner avatar"
                                    >
                                </picture>
                            </div>
                            <div v-else class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl font-medium">
                                {{ (community.owner?.name || community.owner?.email || '?')[0].toUpperCase() }}
                            </div>
                            <div>
                                <p class="font-medium">{{ community.owner?.name || community.owner?.email }}</p>
                                <p v-if="community.owner?.name" class="text-gray-500">{{ community.owner?.email }}</p>
                            </div>
                        </div>
                        <div v-if="canManageCurators" class="flex items-center gap-4">
                            <div v-if="pendingNewOwner" class="text-orange-500">
                                Update to transfer to {{ pendingNewOwner.name }}
                                <button 
                                    @click="pendingNewOwner = null; newOwnerId = null"
                                    class="ml-2 text-gray-500 hover:text-gray-700"
                                >
                                    Cancel
                                </button>
                            </div>
                            <button 
                                v-if="localCurators.length > 0 && !pendingNewOwner"
                                @click="showTransferOwnership = true"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50"
                            >
                                Transfer Ownership
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Curators Section -->
                <div class="border rounded-2xl p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold">Curators</h3>
                        <button 
                            v-if="canManageCurators"
                            @click="showAddCurator = true"
                            class="px-4 py-2 border rounded-lg hover:bg-gray-50"
                        >
                            Add Curator
                        </button>
                    </div>

                    <!-- Curators List -->
                    <div class="space-y-4">
                        <div 
                            v-for="curator in localCurators" 
                            :key="curator.id"
                            class="flex items-center justify-between p-4 border rounded-xl"
                        >
                            <div class="flex items-center gap-4">
                                <div v-if="curator.avatar" class="w-12 h-12">
                                    <picture>
                                        <source :srcset="curator.avatar" type="image/webp">
                                        <img 
                                            :src="curator.avatar.replace(/\.webp$/, '.jpg')"
                                            class="w-12 h-12 rounded-full"
                                            alt="Curator avatar"
                                        >
                                    </picture>
                                </div>
                                <div v-else class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl font-medium">
                                    {{ (curator.name || curator.email || '?')[0].toUpperCase() }}
                                </div>
                                <div>
                                    <p class="font-medium">{{ curator.name || curator.email }}</p>
                                    <p v-if="curator.name" class="text-gray-500">{{ curator.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div v-if="pendingRemoval && pendingRemoval.id === curator.id" class="text-orange-500">
                                    Update to remove {{ curator.name }}
                                    <button 
                                        @click="pendingRemoval = null"
                                        class="ml-2 text-gray-500 hover:text-gray-700"
                                    >
                                        Cancel
                                    </button>
                                </div>
                                <button 
                                    v-else-if="canManageCurators || curator.id === user.id"
                                    @click="markForRemoval(curator)"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    {{ curator.id === user.id ? 'Leave' : 'Remove' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modals only shown if canManageCurators -->
            <template v-if="canManageCurators">
                <!-- Add Curator Modal -->
                <div v-if="showAddCurator" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 w-full max-w-lg">
                        <h3 class="text-xl font-semibold mb-4">Invite Curator</h3>
                        <p class="text-gray-500 mb-4">Enter the email address of the person you'd like to invite as a curator.</p>
                        
                        <form @submit.prevent="inviteCurator" class="space-y-4">
                            <div>
                                <input 
                                    type="email"
                                    v-model="curatorEmail"
                                    placeholder="Enter email address"
                                    class="w-full border rounded-lg p-2"
                                    required
                                />
                                <p v-if="inviteError" class="text-red-500 mt-1 text-sm leading-tight">{{ inviteError }}</p>
                            </div>

                            <div class="flex justify-end gap-4">
                                <button 
                                    type="button"
                                    @click="showAddCurator = false"
                                    class="px-4 py-2 border rounded-lg hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit"
                                    class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800"
                                    :disabled="inviting"
                                >
                                    {{ inviting ? 'Sending...' : 'Send Invitation' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transfer Ownership Modal -->
                <div v-if="showTransferOwnership" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 w-full max-w-lg">
                        <h3 class="text-xl font-semibold mb-4">Transfer Ownership</h3>
                        <p class="text-gray-500 mb-6">Select a curator to transfer ownership to. You will remain as a curator.</p>
                        
                        <div class="space-y-4 max-h-64 overflow-y-auto">
                            <div 
                                v-for="curator in localCurators" 
                                :key="curator.id"
                                @click="selectNewOwner(curator)"
                                class="flex items-center gap-4 p-4 border rounded-xl cursor-pointer hover:bg-gray-50"
                            >
                                <div v-if="curator.avatar" class="w-12 h-12">
                                    <picture>
                                        <source :srcset="curator.avatar" type="image/webp">
                                        <img 
                                            :src="curator.avatar.replace(/\.webp$/, '.jpg')"
                                            class="w-12 h-12 rounded-full"
                                            alt="Curator avatar"
                                        >
                                    </picture>
                                </div>
                                <div v-else class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl font-medium">
                                    {{ (curator.name || curator.email || '?')[0].toUpperCase() }}
                                </div>
                                <div>
                                    <p class="font-medium">{{ curator.name || curator.email }}</p>
                                    <p v-if="curator.name" class="text-gray-500">{{ curator.email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-4 mt-6">
                            <button 
                                @click="showTransferOwnership = false"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </main>
</template>

<script setup>
import { ref, inject, onMounted, watch, computed } from 'vue';
import axios from 'axios';

const community = inject('community');
const errors = inject('errors');
const user = inject('user', {}); // Provide empty default value

// Add computed property to check permissions
const canManageCurators = computed(() => {
    if (!user) return false;
    return user.isAdmin || user.isModerator || user.id === community.owner?.id || user.isCommunityMember;
});

const showAddCurator = ref(false);
const showTransferOwnership = ref(false);
const curatorEmail = ref('');
const inviteError = ref('');
const inviting = ref(false);
const localCurators = ref([]);
const newOwnerId = ref(null);
const pendingNewOwner = ref(null);
const pendingRemoval = ref(null);

// Initialize local curators with community curators
onMounted(() => {
    localCurators.value = [...community.curators];
});

// Watch for community changes and update local state
watch(() => community.owner, (newOwner) => {
    if (newOwner?.id === pendingNewOwner.value?.id) {
        pendingNewOwner.value = null;
        newOwnerId.value = null;
    }
}, { deep: true });

const inviteCurator = async () => {
    inviting.value = true;
    inviteError.value = '';
    
    try {
        const response = await axios.post(`/communities/${community.slug}/curators/invite`, {
            email: curatorEmail.value
        });
        
        showAddCurator.value = false;
        curatorEmail.value = '';
        
        // Show success message (you'll need to implement this)
        // toast.success('Invitation sent successfully');
    } catch (error) {
        inviteError.value = error.response?.data?.message || 'Failed to send invitation';
    } finally {
        inviting.value = false;
    }
};

const removeCurator = async (curator) => {
    try {
        const endpoint = curator.id === user.id
            ? `/communities/${community.slug}/curators/self`  // Changed to match route
            : `/communities/${community.slug}/curators/remove`;
            
        const response = await axios.delete(endpoint);  // Changed to DELETE for self-removal
        
        if (response.data) {
            localCurators.value = localCurators.value.filter(c => c.id !== curator.id);
            
            // If removing self, redirect to homepage
            if (curator.id === user.id) {
                window.location.href = '/';
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert(error.response?.data?.message || 'Failed to remove curator');
    }
};

const selectNewOwner = (curator) => {
    if (confirm(`Are you sure you want to transfer ownership to ${curator.name}?`)) {
        pendingNewOwner.value = curator;
        newOwnerId.value = curator.id;
        showTransferOwnership.value = false;
        
        if (!localCurators.value.find(c => c.id === curator.id)) {
            localCurators.value.push(curator);
        }
    }
};

const markForRemoval = (curator) => {
    // Don't allow removing the owner
    if (curator.id === community.owner?.id) {
        alert('Cannot remove the community owner.');
        return;
    }

    // Don't allow removing pending new owner
    if (curator.id === pendingNewOwner.value?.id) {
        alert('Cannot remove the pending new owner. Cancel the ownership transfer first.');
        return;
    }

    // For self-removal, show confirmation
    if (curator.id === user.id) {
        if (confirm('Are you sure you want to leave this community? You will lose access to manage its content.')) {
            removeCurator(curator);  // Direct call to removeCurator
        }
        return;
    }

    // Mark curator for removal
    pendingRemoval.value = curator;
};

// Component API
defineExpose({
    isValid: () => true,
    submitData: () => {
        const remainingCurators = localCurators.value
            .filter(curator => curator.id !== pendingRemoval.value?.id)
            .map(curator => curator.id);
            
        const data = {
            curator_ids: remainingCurators
        };
        
        if (newOwnerId.value) {
            data.new_owner_id = newOwnerId.value;
        }
        
        return data;
    }
});

// Watch for successful updates to clear pending states
watch(() => community.curators, (newCurators) => {
    localCurators.value = [...newCurators];
    pendingRemoval.value = null;
}, { deep: true });
</script>