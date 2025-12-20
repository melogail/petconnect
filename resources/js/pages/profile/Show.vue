<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import {
  User, Mail, Phone, MapPin, Calendar, Edit2, Trash2, Eye, MessageSquare, FileText,
  Star, MessageCircle, Info, Plus, Settings, Bell, EyeOff, MoreVertical, BadgeCheck, ThumbsUp, ThumbsDown, ChevronLeft, ChevronRight
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch  } from 'vue';
import { route } from 'ziggy-js';
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from "@/components/ui/carousel"

const props = defineProps({
  user: Object
});

const userLocation = computed(() => {
  if (!props.user.data.city && !props.user.data.state && !props.user.data.country) {
    return 'N/A';
  }

  return [props.user.data.city, props.user.data.state, props.user.data.country]
    .filter(Boolean)
    .join(', ');
});

// Tabs state
const activeTab = ref('Posts');
const tabs = ref([
  { name: 'Posts', icon: 'FileText', count: 0 },
  { name: 'Reviews', icon: 'Star', count: 0 }
]);

// Initialize tab counts
onMounted(() => {
  updateTabCounts();
});

// Update tab counts when data changes
const updateTabCounts = () => {
  tabs.value[0].count = posts.value.length;
  tabs.value[1].count = reviews.value.length;
};

// Mock user data - replace with actual data from your backend
// const user = {
//   name: 'John Doe',
//   email: 'john.doe@example.com',
//   phone: '+1 (555) 123-4567',
//   location: 'San Francisco, CA',
//   joinDate: '2023-01-15',
//   bio: 'Pet lover and animal rights advocate. Proud parent of two adorable cats and one energetic golden retriever.',
//   profile_photo_path: 'https://randomuser.me/api/portraits/men/1.jpg',
//   rating: 4.7,
//   reviewCount: 23
// };

// Mock reviews data
const reviews = ref([
  {
    id: 1,
    user: {
      name: 'Sarah Johnson',
      avatar: 'https://randomuser.me/api/portraits/women/44.jpg',
      joinDate: '2022-05-10'
    },
    rating: 5,
    date: '2023-10-15',
    content: 'John is an amazing pet sitter! My dog absolutely adores him. He was very professional and sent me regular updates with photos. Highly recommend!',
    likes: 8,
    userLiked: false,
    userDisliked: false
  },
  {
    id: 2,
    user: {
      name: 'Michael Chen',
      avatar: 'https://randomuser.me/api/portraits/men/32.jpg',
      joinDate: '2021-11-22'
    },
    rating: 4,
    date: '2023-09-28',
    content: 'Good experience overall. John was punctual and took good care of my cat. Would use his services again.',
    likes: 3,
    userLiked: true,
    userDisliked: false
  },
  {
    id: 3,
    user: {
      name: 'Emily Rodriguez',
      avatar: 'https://randomuser.me/api/portraits/women/68.jpg',
      joinDate: '2023-02-05'
    },
    rating: 5,
    date: '2023-08-12',
    content: 'Exceptional service! John went above and beyond to make sure my two dogs were happy and well-cared for while I was away. The house was spotless when I returned.',
    likes: 12,
    userLiked: false,
    userDisliked: false
  },
  {
    id: 4,
    user: {
      name: 'David Kim',
      avatar: 'https://randomuser.me/api/portraits/men/45.jpg',
      joinDate: '2023-03-18'
    },
    rating: 5,
    date: '2023-10-20',
    content: 'John was fantastic with our three cats! He followed all our instructions perfectly and sent us daily updates with photos. The cats were clearly very comfortable with him.',
    likes: 5,
    userLiked: false,
    userDisliked: false
  },
  {
    id: 5,
    user: {
      name: 'Lisa Wong',
      avatar: 'https://randomuser.me/api/portraits/women/29.jpg',
      joinDate: '2022-09-05'
    },
    rating: 4,
    date: '2023-10-18',
    content: 'Very reliable pet sitter. Took great care of my senior dog who needs medication. Appreciated the detailed updates and photos. Will definitely book again!',
    likes: 7,
    userLiked: false,
    userDisliked: false
  },
  {
    id: 6,
    user: {
      name: 'Robert Taylor',
      avatar: 'https://randomuser.me/api/portraits/men/22.jpg',
      joinDate: '2023-01-15'
    },
    rating: 5,
    date: '2023-10-10',
    content: 'John is simply the best! He took care of my two energetic puppies for a week and they had a blast. The house was spotless when I returned and the pups were exhausted from all the playtime. 10/10 would recommend!',
    likes: 15,
    userLiked: false,
    userDisliked: false
  }
]);

// New review form data
const newReview = ref({
  rating: 5,
  content: ''
});

// Calculate average rating
const averageRating = computed(() => {
  if (reviews.value.length === 0) return 0;
  const sum = reviews.value.reduce((acc, review) => acc + review.rating, 0);
  return (sum / reviews.value.length).toFixed(1);
});

// Submit new review
const submitReview = () => {
  if (!newReview.value.content.trim()) return;

  const review = {
    id: reviews.value.length + 1,
    user: {
      name: 'Current User', // This would be the logged-in user in a real app
      avatar: 'https://randomuser.me/api/portraits/men/1.jpg',
      joinDate: '2023-01-15'
    },
    rating: newReview.value.rating,
    date: new Date().toISOString().split('T')[0],
    content: newReview.value.content.trim(),
    likes: 0,
    userLiked: false,
    userDisliked: false
  };

  reviews.value.unshift(review);
  newReview.value.content = '';
  newReview.value.rating = 5;
};

// Toggle like/dislike on a review
const toggleLike = (reviewId, action) => {
  const review = reviews.value.find(r => r.id === reviewId);
  if (!review) return;

  if (action === 'like') {
    if (review.userLiked) {
      review.likes--;
      review.userLiked = false;
    } else {
      if (review.userDisliked) {
        review.likes += 2; // Remove dislike and add like
        review.userDisliked = false;
      } else {
        review.likes++;
      }
      review.userLiked = true;
    }
  } else if (action === 'dislike') {
    if (review.userDisliked) {
      review.likes++;
      review.userDisliked = false;
    } else {
      if (review.userLiked) {
        review.likes -= 2; // Remove like and add dislike
        review.userLiked = false;
      } else {
        review.likes--;
      }
      review.userDisliked = true;
    }
  }
};

// Star rating component
const Star = {
  props: {
    rating: {
      type: Number,
      required: true,
      default: 0,
      validator: (value) => value >= 0 && value <= 5
    },
    interactive: {
      type: Boolean,
      default: false
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md', 'lg'].includes(value)
    }
  },
  emits: ['update:rating'],
  setup(props, { emit }) {
    const hoverRating = ref(0);
    const sizeClasses = {
      sm: 'h-4 w-4',
      md: 'h-5 w-5',
      lg: 'h-6 w-6'
    };

    const starClass = computed(() => sizeClasses[props.size] || sizeClasses.md);

    const handleClick = (value) => {
      if (props.interactive) {
        emit('update:rating', value);
      }
    };

    const handleMouseEnter = (value) => {
      if (props.interactive) {
        hoverRating.value = value;
      }
    };

    const handleMouseLeave = () => {
      hoverRating.value = 0;
    };

    const getStarClass = (index) => {
      const currentRating = hoverRating.value || Math.round(props.rating);
      return {
        'text-yellow-400': index <= currentRating,
        'text-gray-300 dark:text-gray-600': index > currentRating,
        'cursor-pointer hover:scale-110 transition-transform': props.interactive
      };
    };

    return {
      hoverRating,
      starClass,
      handleClick,
      handleMouseEnter,
      handleMouseLeave,
      getStarClass
    };
  },
  template: `
    <div class="flex items-center">
      <button
        v-for="i in 5"
        :key="i"
        type="button"
        :class="[starClass, getStarClass(i)]"
        @click="handleClick(i)"
        @mouseenter="handleMouseEnter(i)"
        @mouseleave="handleMouseLeave"
        :disabled="!interactive"
        :aria-label="'4 out of 5'"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          class="w-full h-full"
        >
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      </button>
    </div>
  `
};

// Mock posts data
const posts = ref([
  {
    id: 1,
    title: 'Adorable Golden Retriever Needs a Home',
    date: '2025-10-25',
    status: 'active',
    views: 1245,
    likes: 89,
    comments: 23,
    shares: 12
  },
  {
    id: 2,
    title: 'Looking for a Playmate for My Cat',
    date: '2025-10-20',
    status: 'inactive',
    views: 876,
    likes: 45,
    comments: 15,
    shares: 8
  },
  {
    id: 3,
    title: 'Adoption Event This Weekend',
    date: '2025-10-15',
    status: 'active',
    views: 2156,
    likes: 132,
    comments: 42,
    shares: 31
  }
]);

const formatDate = (dateString: string) => {
  const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' };
  return new Date(dateString).toLocaleDateString('en-US', options);
};

const deletePost = (postId: number) => {
  // Handle post deletion
  posts.value = posts.value.filter(post => post.id !== postId);
};

const togglePostStatus = (postId: number) => {
  // Toggle post status
  posts.value = posts.value.map(post =>
    post.id === postId
      ? { ...post, status: post.status === 'active' ? 'inactive' : 'active' }
      : post
  );
};

// Update tab counts when posts or reviews change
watch([posts, reviews], () => {
    updateTabCounts();
});
</script>

<template>
  <Head title="Profile" />
  <MainLayout class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Enhanced Profile Header -->
      <div class="bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-gray-800 dark:to-gray-900 rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-gray-700">
        {{ user }}
        <div class="relative px-6 py-8 sm:p-10">
          <div class="flex flex-col lg:flex-row items-start gap-8">
            <!-- Profile Picture with Decorative Border -->
            <div class="relative group">
              <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500 transform rotate-3 scale-105 opacity-60 group-hover:opacity-80 transition-all duration-300"></div>
              <div v-if="user.data.avatar" class="relative h-36 w-36 rounded-2xl overflow-hidden border-4 border-white dark:border-gray-800 shadow-xl">
                <img 
                  :src="user.data.avatar"
                  :alt="user.data.name"
                  class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              </div>
              <div v-else>
                <div class="relative h-36 w-36 rounded-2xl overflow-hidden border-4 border-white dark:border-gray-800 shadow-xl flex items-center justify-center">
                  <div class="absolute inset-0 bg-gradient-to-br from-indigo-400 to-violet-500 transform rotate-3 scale-105 opacity-60 group-hover:opacity-80 transition-all duration-300"></div>
                  <span class="relative text-4xl font-bold text-white uppercase">
                    {{ user.data.name.slice(0, 2) }}
                  </span>
                  <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </div>
              </div>
              <button
                class="absolute -bottom-3 -right-3 bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 p-2 rounded-full shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 border-2 border-white dark:border-gray-700"
                title="Update photo"
              >
                <Edit2 class="h-4 w-4" />
              </button>
            </div>

            <!-- User Info -->
            <div class="flex-1 min-w-0 space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                      <h1 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent">
                        {{ user.data.name }}
                      </h1>
                      <span v-if="!user.data.is_verified" class="text-violet-500 dark:text-violet-400" title="Verified account">
                        <BadgeCheck class="w-6 h-6 text-green-500" :size="24" />
                      </span>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                      <User class="h-3 w-3 mr-1" />
                      Member
                    </span>
                  </div>
                  <p class="text-indigo-500 dark:text-indigo-400 text-sm flex items-center">
                    <Calendar class="h-4 w-4 mr-1.5" />
                    Member since {{ user.data.created_at }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-3">
                  <Link
                    :href="'#'"
                    class="inline-flex items-center px-4 py-2.5 bg-white/80 dark:bg-gray-700/80 backdrop-blur-sm border border-gray-200/50 dark:border-gray-600/50 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-600 transition-all hover:shadow-md hover:-translate-y-0.5"
                  >
                    <MessageSquare class="h-4 w-4 mr-2 text-indigo-500 dark:text-indigo-400" />
                    Messages
                  </Link>
                  <Link
                    :href="'#'"
                    class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 border-0 text-sm font-medium text-white hover:from-indigo-700 hover:to-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all hover:shadow-lg hover:-translate-y-0.5 rounded-xl"
                  >
                    <Edit2 class="h-4 w-4 mr-2 text-white/90" />
                    Edit Profile
                  </Link>
                </div>
              </div>

              <!-- User Details Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-6">
                <div class="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm p-3 rounded-xl border border-gray-100/50 dark:border-gray-700/50 hover:bg-white dark:hover:bg-gray-700/70 transition-colors">
                  <div class="flex items-center">
                    <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg mr-3">
                      <Mail class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 dark:text-gray-400">Email</p>
                      <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.data.email }}</p>
                    </div>
                  </div>
                </div>
                <div class="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm p-3 rounded-xl border border-gray-100/50 dark:border-gray-700/50 hover:bg-white dark:hover:bg-gray-700/70 transition-colors">
                  <div class="flex items-center">
                    <div class="p-2 bg-violet-50 dark:bg-violet-900/30 rounded-lg mr-3">
                      <Phone class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 dark:text-gray-400">Phone</p>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ user.data.phone ?? 'N/A' }}</p>
                    </div>
                  </div>
                </div>
                <div class="bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm p-3 rounded-xl border border-gray-100/50 dark:border-gray-700/50 hover:bg-white dark:hover:bg-gray-700/70 transition-colors">
                  <div class="flex items-center">
                    <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg mr-3">
                      <MapPin class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 dark:text-gray-400">Location</p>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ userLocation }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bio -->
              <div class="mt-6 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm p-4 rounded-xl border border-gray-100/50 dark:border-gray-700/50">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">About</h3>
                <p v-if="user.data.about" class="text-gray-600 dark:text-gray-300 leading-relaxed">
                  {{ user.data.about }}
                </p>
                <p v-else class="text-gray-600 dark:text-gray-300 leading-relaxed">
                  <span class="flex items-center italic text-gray-400 dark:text-gray-500">
                    <Info class="h-4 w-4 mr-2" />
                    No bio information provided yet.
                  </span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="mt-8">
        <div class="border-b border-gray-200 dark:border-gray-700">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.name"
              @click="activeTab = tab.name"
              :class="[
                activeTab === tab.name
                  ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center'
              ]"
            >
              <component :is="tab.icon" class="h-5 w-5 mr-2" />
              {{ tab.name }}
              <span v-if="tab.count !== undefined" class="ml-2 bg-gray-100 dark:bg-gray-700 rounded-full px-2.5 py-0.5 text-xs font-medium">
                {{ tab.count }}
              </span>
            </button>
          </nav>
        </div>
      </div>

      <!-- Tab Content -->
      <div class="mt-6">
        <!-- Posts Tab -->
        <div v-show="activeTab === 'Posts'" class="space-y-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Your Posts</h2>
            <Link
              :href="'#'"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
            >
              <Plus class="h-4 w-4 mr-2" />
              Create Post
            </Link>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-700/80 backdrop-blur-sm">
                  <tr>
                    <th scope="col" class="w-16 px-4 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider font-sans">

                    </th>
                    <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider font-sans">
                      Post Title
                    </th>
                    <th scope="col" class="px-4 py-4 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider font-sans">
                      Status
                    </th>
                    <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider font-sans">
                      Created
                    </th>
                    <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider font-sans">
                      Views
                    </th>
                    <th scope="col" class="px-6 py-4 text-right">
                      <span class="sr-only">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700/50">
                  <tr
                    v-for="post in posts"
                    :key="post.id"
                    class="group hover:bg-gray-50/80 dark:hover:bg-gray-700/50 transition-colors duration-150"
                  >
                    <td class="px-4 py-4">
                      <div class="flex items-center justify-center">
                        <div class="relative w-10 h-10 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                          <img
                            v-if="post.thumbnail"
                            :src="post.thumbnail"
                            :alt="post.title"
                            class="w-full h-full object-cover"
                          />
                          <div v-else class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-600">
                            <FileText class="h-5 w-5 text-gray-400" />
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="flex items-center">
                        <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors duration-150">
                          {{ post.title }}
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <span :class="[
                        'px-2.5 py-1 inline-flex items-center text-xs font-medium rounded-full',
                        'transition-all duration-200 transform group-hover:scale-105',
                        post.status === 'active'
                          ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                          : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                      ]">
                        <span :class="[
                          'w-1.5 h-1.5 rounded-full mr-1.5',
                          post.status === 'active'
                            ? 'bg-green-500 dark:bg-green-400'
                            : 'bg-yellow-500 dark:bg-yellow-400'
                        ]"></span>
                        {{ post.status === 'active' ? 'Active' : 'Draft' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-600 dark:text-gray-300">
                        {{ formatDate(post.date) }}
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                        <Eye class="h-4 w-4 mr-1.5 text-gray-400 group-hover:text-indigo-500 transition-colors" />
                        <span class="font-medium">{{ post.views.toLocaleString() }}</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center justify-end space-x-3">
                        <Link
                          :href="`/posts/${post.id}/edit`"
                          class="p-1.5 rounded-full text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors duration-150"
                          title="Edit post"
                        >
                          <Edit2 class="h-4 w-4" />
                        </Link>
                        <button
                          @click="togglePostStatus(post.id)"
                          class="p-1.5 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                          :title="post.status === 'active' ? 'Hide post' : 'Publish post'"
                        >
                          <EyeOff v-if="post.status === 'active'" class="h-4 w-4" />
                          <Eye v-else class="h-4 w-4" />
                        </button>
                        <button
                          @click="deletePost(post.id)"
                          class="p-1.5 rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-gray-700 transition-colors duration-150"
                          title="Delete post"
                        >
                          <Trash2 class="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="posts.length === 0" class="text-center py-12">
              <FileText class="h-12 w-12 mx-auto text-gray-400" />
              <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">No posts yet</h3>
              <p class="mt-1 text-gray-500 dark:text-gray-400">Get started by creating a new post</p>
            </div>
          </div>
        </div>

        <!-- Reviews Tab -->
        <div v-show="activeTab === 'Reviews'" class="space-y-8">
          <!-- Add Review Form -->
          <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Write a Review</h3>
            <form @submit.prevent="submitReview" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Your Rating
                </label>
                <div class="flex items-center space-x-1">
                  <button
                    v-for="i in 5"
                    :key="i"
                    type="button"
                    @click="newReview.rating = i"
                    class="focus:outline-none"
                  >
                    <svg
                      :class="[
                        'h-8 w-8',
                        i <= newReview.rating
                          ? 'text-yellow-400 fill-current'
                          : 'text-gray-300 dark:text-gray-600 fill-current'
                      ]"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  </button>
                </div>
              </div>
              <div>
                <label for="review-content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Your Review
                </label>
                <textarea
                  id="review-content"
                  v-model="newReview.content"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 dark:bg-gray-700 dark:text-white"
                  placeholder="Share your experience..."
                  required
                ></textarea>
              </div>
              <div class="flex justify-end">
                <button
                  type="submit"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
                >
                  Submit Review
                </button>
              </div>
            </form>
          </div>

          <!-- Reviews Carousel -->
          <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                Reviews <span class="text-gray-500 dark:text-gray-400">({{ reviews.length }})</span>
              </h3>
              <div class="flex items-center">
                <div class="flex items-center mr-4">
                  <div class="flex items-center">
                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ averageRating }}</span>
                    <span class="mx-1.5 text-gray-500 dark:text-gray-400">·</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ reviews.length }} reviews</span>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="reviews.length > 0" class="relative px-12">
              <Carousel
                :opts="{
                  align: 'start',
                  loop: true,
                  slidesToScroll: 'auto'
                }"
                class="w-full"
              >
                <div class="relative">
                  <CarouselPrevious class="-left-12 h-10 w-10 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700" />
                  <CarouselNext class="-right-12 h-10 w-10 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700" />
                </div>
                <CarouselContent class="-ml-1">
                  <CarouselItem
                    v-for="review in reviews"
                    :key="review.id"
                    class="md:basis-1/2 lg:basis-1/3"
                  >
                    <div class="p-1">
                      <div class="bg-gray-50 dark:bg-gray-700/50 p-6 rounded-lg border border-gray-100 dark:border-gray-700 h-full flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                          <div class="flex items-center space-x-3">
                            <img :src="review.user.avatar" :alt="review.user.name" class="h-10 w-10 rounded-full">
                            <div>
                              <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ review.user.name }}</h4>
                              <div class="flex items-center">
                                <div class="flex">
                                  <svg v-for="i in 5" :key="i"
                                    :class="[
                                      'h-4 w-4',
                                      i <= review.rating ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600',
                                      'fill-current'
                                    ]"
                                    viewBox="0 0 20 20"
                                  >
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                  </svg>
                                </div>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(review.date) }}</span>
                              </div>
                            </div>
                          </div>
                          <button class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <MoreVertical class="h-5 w-5" />
                          </button>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed flex-grow">{{ review.content }}</p>
                        <div class="mt-4 flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                          <div class="flex items-center space-x-2">
                            <button
                              @click="toggleLike(review.id, 'like')"
                              class="flex items-center text-xs text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400"
                            >
                              <ThumbsUp :class="['h-4 w-4 mr-1', review.userLiked ? 'fill-violet-500 text-violet-500' : '']" />
                              <span>{{ review.likes }}</span>
                            </button>
                          </div>
                          <button class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 flex items-center">
                            <MessageCircle class="h-4 w-4 mr-1" />
                            <span>Reply</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </CarouselItem>
                </CarouselContent>
              </Carousel>
            </div>

            <div v-else class="text-center py-12">
              <Star class="h-12 w-12 mx-auto text-gray-400" />
              <p class="mt-1 text-gray-500 dark:text-gray-400">Be the first to leave a review</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
