<template>
    <aside
        class="sticky pl-6 py-4 top-20 h-[calc(100vh-5rem)] overflow-y-auto overflow-x-hidden border-r bg-card/50 backdrop-blur-sm scrollbar-thin scrollbar-track-transparent scrollbar-thumb-muted-foreground/20 hover:scrollbar-thumb-muted-foreground/30 scrollbar-thumb-rounded-full transition-colors duration-200"
        style="scrollbar-gutter: stable"
    >
        <div class="space-y-8">
            <div>
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-lg font-bold">
                        <Filter class="h-5 w-5 text-primary" />
                        Filters
                    </h2>
                    <button
                        @click="clearAllFilters"
                        class="rounded-md px-3 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted/50"
                    >
                        Clear all
                    </button>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Animal Type -->
                <div class="mt-6">
                    <h3
                        class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Animal Type
                    </h3>
                    <div class="space-y-3">
                        <label
                            v-for="category in categories"
                            :key="category.id"
                            class="group flex cursor-pointer items-center justify-between rounded-xl p-3 transition-colors hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :id="category.id"
                                    v-model="selectedCategories"
                                    :value="category.id"
                                    class="checkbox-custom"
                                />
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="category.icon"
                                        class="h-4 w-4 text-muted-foreground transition-colors group-hover:text-primary"
                                    />
                                    <span class="text-sm font-medium">{{
                                        category.label
                                    }}</span>
                                </div>
                            </div>
                            <span
                                class="rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground"
                            >
                                {{ category.count }}
                            </span>
                        </label>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                <!-- Age Range -->
                <div>
                    <h3
                        class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Age Range
                    </h3>
                    <div class="px-2">
                        <div class="relative flex h-8 w-full items-center">
                            <!-- Track -->
                            <div
                                class="absolute h-1.5 w-full rounded-full bg-muted"
                            ></div>
                            <!-- Active range -->
                            <div
                                class="pointer-events-none absolute h-1.5 rounded-full bg-primary"
                                :style="{
                                    left: `${(ageRange[0] / 15) * 100}%`,
                                    right: `${100 - (ageRange[1] / 15) * 100}%`,
                                }"
                            ></div>

                            <!-- Min value slider -->
                            <input
                                type="range"
                                v-model.number="ageRange[0]"
                                :min="0"
                                :max="15"
                                step="1"
                                class="range-input min-value"
                                @input="adjustMinRange"
                            />

                            <!-- Max value slider -->
                            <input
                                type="range"
                                v-model.number="ageRange[1]"
                                :min="0"
                                :max="15"
                                step="1"
                                class="range-input max-value"
                                @input="adjustMaxRange"
                            />

                        </div>
                        <div
                            class="mt-3 flex justify-between text-xs text-muted-foreground"
                        >
                            <span
                                >{{ ageRange[0] }} year{{
                                    ageRange[0] !== 1 ? 's' : ''
                                }}</span
                            >
                            <span
                                >{{ ageRange[1] }} year{{
                                    ageRange[1] !== 1 ? 's' : ''
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                <!-- Adoption Type -->
                <div>
                    <h3
                        class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Adoption Type
                    </h3>
                    <div class="space-y-3">
                        <label
                            v-for="type in adoptionTypes"
                            :key="type"
                            class="flex cursor-pointer items-center gap-3 rounded-xl p-3 transition-colors hover:bg-muted/50"
                        >
                            <input
                                type="checkbox"
                                :id="type"
                                v-model="selectedAdoptionTypes"
                                :value="type"
                                class="checkbox-custom"
                            />
                            <span class="text-sm font-medium">{{ type }}</span>
                        </label>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                <!-- Location -->
                <div>
                    <h3
                        class="mb-4 flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <MapPin class="h-4 w-4" />
                        Location
                    </h3>
                    <div class="space-y-2">
                        <label
                            v-for="location in locations"
                            :key="location"
                            class="flex cursor-pointer items-center gap-3 rounded-lg p-2 transition-colors hover:bg-muted/50"
                        >
                            <input
                                type="checkbox"
                                :id="location"
                                v-model="selectedLocations"
                                :value="location"
                                class="checkbox-custom"
                            />
                            <span class="text-sm">{{ location }}</span>
                        </label>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                <!-- Vaccination Status -->
                <div>
                    <h3
                        class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Vaccination Status
                    </h3>
                    <div class="space-y-3">
                        <label
                            v-for="status in vaccinationStatuses"
                            :key="status"
                            class="flex cursor-pointer items-center gap-3 rounded-xl p-3 transition-colors hover:bg-muted/50"
                        >
                            <input
                                type="checkbox"
                                :id="status"
                                v-model="selectedVaccinationStatuses"
                                :value="status"
                                class="checkbox-custom"
                            />
                            <span class="text-sm font-medium">{{
                                status
                            }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { ref } from 'vue';
import { Dog, Cat, Bird, Trophy, MapPin, Filter } from 'lucide-vue-next';

const categories = [
    { id: 'dogs', label: 'Dogs', icon: Dog, count: 124 },
    { id: 'cats', label: 'Cats', icon: Cat, count: 98 },
    { id: 'birds', label: 'Birds', icon: Bird, count: 45 },
    { id: 'horses', label: 'Horses', icon: Trophy, count: 23 },
];

const locations = [
    'Cairo, Egypt',
    'London, UK',
    'New York, USA',
    'Toronto, Canada',
    'Berlin, Germany',
    'Sydney, Australia',
];

const adoptionTypes = ['Adoption', 'For Sale', 'Both'];
const vaccinationStatuses = ['Vaccinated', 'Not Vaccinated', 'Unknown'];

// Reactive state
const selectedCategories = ref([]);
const ageRange = ref([1, 10]);
const selectedAdoptionTypes = ref([]);
const selectedLocations = ref([]);
const selectedVaccinationStatuses = ref([]);

// Adjust range values to prevent overlap
const adjustMinRange = () => {
    if (ageRange.value[0] > ageRange.value[1]) {
        ageRange.value[0] = ageRange.value[1];
    }
};

const adjustMaxRange = () => {
    if (ageRange.value[1] < ageRange.value[0]) {
        ageRange.value[1] = ageRange.value[0];
    }
};

// Methods
const clearAllFilters = () => {
    selectedCategories.value = [];
    ageRange.value = [1, 10];
    selectedAdoptionTypes.value = [];
    selectedLocations.value = [];
    selectedVaccinationStatuses.value = [];
};
</script>

<style scoped>
/* ===========================
   ✅ Custom Checkbox Styling
   =========================== */
.checkbox-custom {
    appearance: none;
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(42, 57, 81, 0.50);
    border-radius: 0.5rem;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
    background-color: white;
}

.checkbox-custom:hover {
    border-color: #8A2CE2FF;
}

.checkbox-custom:checked {
    background-color: #8A2CE2FF;
    border-color: #8A2CE2FF;
}

.checkbox-custom:checked::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(45deg);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
}

.checkbox-custom:focus {
    outline: none;
    box-shadow: 0 0 0 2px hsl(var(--primary) / 0.2);
}

/* ===========================
   Custom Scrollbar Styling
   =========================== */
/* For WebKit browsers (Chrome, Safari, etc.) */
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: hsl(var(--muted-foreground) / 0.2);
    border-radius: 3px;
    transition: background-color 0.2s ease;
}

.scrollbar-thin:hover::-webkit-scrollbar-thumb {
    background-color: hsl(var(--muted-foreground) / 0.3);
}

/* For Firefox */
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--muted-foreground) / 0.2) transparent;
}

.scrollbar-thin:hover {
    scrollbar-color: hsl(var(--muted-foreground) / 0.3) transparent;
}

/* ===========================
   Dual Range Slider Styling
   =========================== */
.range-wrapper {
    position: relative;
    width: 100%;
    height: 24px;
    display: flex;
    align-items: center;
}

/* Base range input styles */
.range-input {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 24px;
    position: absolute;
    top: 0;
    margin: 0;
    pointer-events: none;
}

/* Thumbs */
.range-input::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    background: white;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
    cursor: pointer;
    pointer-events: auto;
    position: relative;
    top: 3px;
    z-index: 10;
    border-radius: 12px;
    border: 3px solid  #8A2CE2FF;
    transition:
        background 0.2s,
        transform 0.2s;
}


/* Transparent tracks */
.range-input::-webkit-slider-runnable-track {
    -webkit-appearance: none;
    background: transparent;
}

.range-input::-moz-range-track {
    background: transparent;
}

/* Second thumb always above the first */
.range-input:nth-of-type(2)::-webkit-slider-thumb {
    z-index: 20;
}

.range-input:nth-of-type(2)::-moz-range-thumb {
    z-index: 20;
}
</style>
