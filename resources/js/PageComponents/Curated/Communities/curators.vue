<template>
    <div class="p-4 rounded-2xl my-4 relative bg-gray-300">
        <h4 class="mt-8 text-2xl">Curators</h4>
        <div class="overflow-auto flex flex-col pb-8">
            <div class="items-center inline-flex mb-2 h-14">
                <div class="flex w-96 items-center relative mr-2">
                    <p class="text-xl">{{ owner.name }} (Owner)</p>
                </div>
            </div>
            <div 
                v-for="curator in curators"
                :key="curator.id"
                class="items-center inline-flex h-10">
                <div class="flex w-96 items-center relative mr-2">
                    <p class="text-lg mr-2">{{ curator.name }}</p> 
                    <button 
                        v-if="isOwner"
                        @click="updateOwner(curator)"
                        class="border-none underline text-lg">
                        (Make Owner)
                    </button>
                </div>
                <div 
                    v-if="isOwner"
                    class="flex w-40 items-center relative mr-2">
                    <button 
                        @click="removeCurator(curator)"
                        class="border-none underline text-lg">
                        Remove
                    </button>
                </div>
            </div>
        </div>
        <button 
            class="rounded-full py-2 px-4 bg-white hover:bg-black hover:text-white hover:border-black"
            @click="showInviteForm = true">Invite New Curator</button>
        <div 
            class="mt-4" 
            v-if="showInviteForm">
            <div class="field">
                <input 
                    type="text" 
                    v-model="curator"
                    @input="clearErrors"
                    :class="{ 'error': v$.curator.$error }"
                    placeholder="curator email">
                <div v-if="v$.curator.$error" class="validation-error">
                    <p class="error" v-if="!v$.curator.required">Please add a name.</p>
                    <p class="error" v-if="!v$.curator.email">Must be an email</p>
                </div>
            </div>
            <button 
                class="rounded-full py-2 px-4 bg-white hover:bg-black hover:text-white hover:border-black"
                @click="addCurator">
                Submit
            </button>
        </div>
        <div 
            v-if="serverErrors" 
            class="fixed top-8 right-8 p-8 bg-green-400 rounded-2xl z-[2005]">
            <transition-group name="slide-fade">
                <ul 
                    v-for="(error, index) in serverErrors"
                    :key="`name${index}`">
                    <li>
                        <p> {{ error.toString() }}</p>
                    </li>
                </ul>
            </transition-group>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email } from '@vuelidate/validators'

// Props
const props = defineProps({
    loadcurators: {
        type: Array,
        required: true
    },
    loadowner: {
        type: Object,
        required: true
    },
    community: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
})

// Emits
const emit = defineEmits(['update'])

// Data
const curators = ref(props.loadcurators.filter(u => u.id !== props.loadowner.id))
const serverErrors = ref(null)
const owner = ref(props.loadowner)
const showInviteForm = ref(false)
const curator = ref(null)
const isOwner = computed(() => props.user.id === props.loadowner.id)

// Validation rules
const rules = {
    curator: { required, email }
}

// Setup vuelidate
const v$ = useVuelidate(rules, { curator })

// Methods
const addCurator = async () => {
    const isValid = await v$.value.$validate()
    if (!isValid) return

    try {
        const res = await axios.post(`/communities/${props.community.slug}/curators/add`, {
            email: curator.value
        })
        showInviteForm.value = false
        curators.value = res.data.curators.filter(u => u.id !== owner.value.id)
        emit("update", owner.value, curators.value)
    } catch (err) {
        serverErrors.value = err.response?.data?.errors || { error: err.message }
        setTimeout(() => serverErrors.value = null, 10000)
    }
}

const updateOwner = async (curator) => {
    try {
        const res = await axios.post(`/communities/${props.community.slug}/curators/owner`, curator)
        owner.value = res.data.owner
        curators.value = res.data.curators.filter(u => u.id !== owner.value.id)
        emit("update", owner.value, curators.value)
    } catch (err) {
        serverErrors.value = err.response?.data?.errors || { error: err.message }
    }
}

const removeCurator = async (curator) => {
    try {
        const res = await axios.post(`/communities/${props.community.slug}/curators/remove`, curator)
        owner.value = res.data.owner
        curators.value = res.data.curators.filter(u => u.id !== owner.value.id)
        emit("update", owner.value, curators.value)
    } catch (err) {
        serverErrors.value = err.response?.data?.errors || { error: err.message }
    }
}

const clearErrors = () => {
    v$.value.$touch()
    serverErrors.value = null
}
</script>