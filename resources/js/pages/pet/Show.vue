<script setup lang="ts">
import MainLayout from '@/layouts/MainLayout.vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { Separator } from '@/components/ui/separator';
import { Heart, MessageSquare, MoreHorizontal, MoreVertical, Send, ThumbsUp, Reply, Edit, Trash2, Flag } from 'lucide-vue-next';
import ReportDialog from '@/components/web/ReportDialog.vue';


const pet = defineProps({
  pet: {
    type: Object,
    required: true
  }
});

defineEmits(['toggle-reply', 'add-reply', 'edit-comment', 'delete-comment', 'report-content', 'toggle-like']);


// Dialog states
const showMessageDialog = ref(false);
const reportReason = ref('');
const messageContent = ref('');
const selectedComment = ref(null);
const isEditing = ref(false);
const editContent = ref('');

// Report dialog state
const reportDialogOpen = ref(false);
const reportContentType = ref('');
const reportContentId = ref<string | number | null>(null);

// Open report dialog
const openReportDialog = (type: string, id: string | number) => {
    reportContentType.value = type;
    reportContentId.value = id;
    reportDialogOpen.value = true;
};

// Close report dialog
const closeReportDialog = () => {
    reportDialogOpen.value = false;
    reportContentType.value = '';
    reportContentId.value = null;
};

// Handle report submission
const handleReportSubmit = (reportData: any) => {
    console.log('Report submitted:', reportData);
    // In a real app, you would send this to your backend
    // await axios.post('/api/reports', reportData);

    alert('Thank you for your report. We will review it shortly.');
    closeReportDialog();
};



// Handle comment actions
const onCommentAction = (action, comment) => {
  selectedComment.value = comment;

  switch (action) {
    case 'edit':
      isEditing.value = true;
      editContent.value = comment.content;
      break;
    case 'delete':
      if (confirm('Are you sure you want to delete this comment?')) {
        // Handle delete comment
        const index = comments.value.findIndex(c => c.id === comment.id);
        if (index !== -1) {
          comments.value.splice(index, 1);
        }
      }
      break;
    case 'report':
      showReportDialog.value = true;
      break;
  }
};

// Handle report submission
const submitReport = () => {
  if (reportReason.value.trim()) {
    // Here you would typically make an API call to report the comment
    console.log('Reported comment:', selectedComment.value.id, 'Reason:', reportReason.value);
    showReportDialog.value = false;
    reportReason.value = '';
    // Show success message
    alert('Thank you for your report. We will review it shortly.');
  }
};

// Handle sending a message
const sendMessage = () => {
  if (messageContent.value.trim()) {
    // Here you would typically make an API call to send the message
    console.log('Message to owner:', messageContent.value);
    showMessageDialog.value = false;
    messageContent.value = '';
    // Show success message
    alert('Your message has been sent!');
  }
};

// Save edited comment
const saveEditedComment = () => {
  if (editContent.value.trim() && selectedComment.value) {
    selectedComment.value.content = editContent.value;
    isEditing.value = false;
    editContent.value = '';
    selectedComment.value = null;
  }
};

// Sample data - replace with actual data from props
const owner = {
  name: 'John Doe',
  avatar: 'https://randomuser.me/api/portraits/men/1.jpg',
  location: 'New York, NY',
  memberSince: '2023',
  rating: 4.8,
  verified: true
};

const petDetails = {
  name: 'Max',
  breed: 'Golden Retriever',
  age: '2 years',
  gender: 'Male',
  size: 'Medium',
  vaccinated: true,
  spayedNeutered: true,
  goodWithKids: true,
  goodWithPets: true,
  description: 'Max is a friendly and energetic Golden Retriever who loves playing fetch and going for long walks. He is house-trained, knows basic commands, and gets along well with children and other pets.'
};

const carouselImages = [
  'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1537151625747-768eb6cf92b2?w=800&auto=format&fit=crop&q=80',
  'https://images.unsplash.com/photo-1583511655826-057004d81245?w=800&auto=format&fit=crop&q=80',
];

const carouselRef = ref(null);
const currentSlide = ref(0);

const goToSlide = (index) => {
  if (carouselRef.value) {
    carouselRef.value.scrollTo({ left: carouselRef.value.offsetWidth * index, behavior: 'smooth' });
    currentSlide.value = index;
  }
};

const handleSlideChange = (index) => {
  currentSlide.value = index;
};

const comments = ref([
  {
    id: 1,
    user: {
      name: 'Sarah Johnson',
      avatar: 'https://randomuser.me/api/portraits/women/1.jpg',
      verified: true
    },
    content: 'What a beautiful dog! How is he with other dogs?',
    timestamp: '2 hours ago',
    likes: 5,
    isLiked: false,
    replies: [
      {
        id: 2,
        user: {
          name: 'John Doe',
          avatar: 'https://randomuser.me/api/portraits/men/1.jpg',
          verified: true
        },
        content: 'Thanks! He gets along very well with other dogs, especially after a proper introduction.',
        timestamp: '1 hour ago',
        likes: 2,
        isLiked: false
      }
    ]
  },
  {
    id: 3,
    user: {
      name: 'Mike Wilson',
      avatar: 'https://randomuser.me/api/portraits/men/2.jpg',
      verified: false
    },
    content: 'Is he house trained?',
    timestamp: '3 hours ago',
    likes: 1,
    isLiked: false,
    replies: []
  }
]);

const newComment = ref('');
const replyTo = ref(null);
const replyContent = ref('');
const showReplyInput = ref(false);
const activeReplyId = ref(null);

const handleLike = (comment) => {
  comment.isLiked = !comment.isLiked;
  comment.likes += comment.isLiked ? 1 : -1;
};

const handleReply = (comment) => {
  activeReplyId.value = comment.id === activeReplyId.value ? null : comment.id;
  replyTo.value = comment.user.name;
};

const submitComment = () => {
  if (!newComment.value.trim()) return;

  const newCommentObj = {
    id: Date.now(),
    user: {
      name: 'Current User',
      avatar: 'https://randomuser.me/api/portraits/men/3.jpg',
      verified: true
    },
    content: newComment.value,
    timestamp: 'Just now',
    likes: 0,
    isLiked: false,
    replies: []
  };

  if (activeReplyId.value) {
    const parentComment = comments.value.find(c => c.id === activeReplyId.value || c.replies.some(r => r.id === activeReplyId.value));
    if (parentComment) {
      parentComment.replies.push(newCommentObj);
    }
  } else {
    comments.value.unshift(newCommentObj);
  }

  newComment.value = '';
  activeReplyId.value = null;
};

const submitReply = (commentId) => {
  if (!replyContent.value.trim()) return;

  const parentComment = comments.value.find(c => c.id === commentId);
  if (parentComment) {
    parentComment.replies.push({
      id: Date.now(),
      user: {
        name: 'Current User',
        avatar: 'https://randomuser.me/api/portraits/men/3.jpg',
        verified: true
      },
      content: replyContent.value,
      timestamp: 'Just now',
      likes: 0,
      isLiked: false
    });

    replyContent.value = '';
    activeReplyId.value = null;
  }
};
</script>

<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto w-full px-6 py-8">
      <!-- Back to listing link -->
      <div class="mb-6">
        <a href="#" class="text-primary hover:underline flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
          </svg>
          Back to Pets
        </a>
      </div>

      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content -->
        <div class="lg:w-2/3">
          <!-- Carousel -->
          <div class="mb-8">
            <div class="relative w-full mb-4 rounded-lg overflow-hidden shadow-lg" ref="carouselContainer">
              <div
                ref="carouselRef"
                class="flex transition-transform duration-300 ease-out overflow-hidden rounded-lg"
                @scroll="handleScroll"
              >
                <div
                  v-for="(image, index) in carouselImages"
                  :key="index"
                  class="w-full flex-shrink-0"
                >
                  <div class="relative pt-[56.25%] bg-gray-100">
                    <img
                      :src="image"
                      :alt="`${petDetails.name} image ${index + 1}`"
                      class="absolute inset-0 w-full h-full object-cover"
                      @load="() => handleSlideChange(index)"
                    />
                  </div>
                </div>
              </div>

              <!-- Navigation Arrows -->
              <button
                @click="goToSlide(currentSlide - 1)"
                :disabled="currentSlide === 0"
                class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :class="{ 'opacity-0': currentSlide === 0 }"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                @click="goToSlide(currentSlide + 1)"
                :disabled="currentSlide === carouselImages.length - 1"
                class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :class="{ 'opacity-0': currentSlide === carouselImages.length - 1 }"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>

            <!-- Thumbnails -->
            <div class="flex gap-2 overflow-x-auto pb-2">
              <button
                v-for="(image, index) in carouselImages"
                :key="`thumb-${index}`"
                @click="goToSlide(index)"
                class="flex-shrink-0 w-16 h-16 rounded-md overflow-hidden border-2 transition-all duration-200"
                :class="{ 'border-primary scale-105': currentSlide === index, 'border-transparent hover:border-gray-300': currentSlide !== index }"
              >
                <img
                  :src="image"
                  :alt="`Thumbnail ${index + 1}`"
                  class="w-full h-full object-cover"
                  :class="{ 'opacity-100': currentSlide === index, 'opacity-70 hover:opacity-100': currentSlide !== index }"
                />
              </button>
            </div>
          </div>

          <!-- Pet Details -->
          <Card class="mb-8 overflow-hidden border-0 shadow-lg p-0">
            <div class="bg-gradient-to-r from-primary/5 to-primary/10 p-6">
              <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                  <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary/80">
                      {{ petDetails.name }}
                    </h1>
                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-primary/10 text-primary">
                      {{ petDetails.breed }}
                    </span>
                  </div>
                  <div class="flex items-center gap-3 text-muted-foreground">
                    <span class="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                      </svg>
                      {{ petDetails.age }}
                    </span>
                    <span class="h-1 w-1 rounded-full bg-muted-foreground/50"></span>
                    <span class="capitalize">{{ petDetails.gender.toLowerCase() }}</span>
                    <span class="h-1 w-1 rounded-full bg-muted-foreground/50"></span>
                    <span class="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                      </svg>
                      New York, NY
                    </span>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <Button variant="outline" class="rounded-full px-6 h-10 group">
                    <Heart class="h-4 w-4 mr-2 group-hover:fill-primary group-hover:text-primary transition-colors" />
                    Save
                  </Button>
                  <Button class="rounded-full px-6 h-10 bg-primary hover:bg-primary/90">
                    <MessageSquare class="h-4 w-4 mr-2" />
                    Contact
                  </Button>
                </div>
              </div>
            </div>

            <CardContent class="p-6">
              <!-- Enhanced Stats Grid -->
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <!-- Vaccination -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white to-muted/30 dark:from-gray-800/50 dark:to-gray-800/20 p-5 shadow-sm border border-muted/20 hover:border-primary/20 hover:shadow-md transition-all duration-300">
                  <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-primary/5 dark:bg-primary/10 group-hover:bg-primary/10 dark:group-hover:bg-primary/20 transition-colors duration-300"></div>
                  <div class="relative z-10">
                    <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-green-100 to-green-50 text-green-600 dark:from-green-900/30 dark:to-green-800/20 dark:text-green-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <p class="text-sm font-medium text-muted-foreground mb-1">Vaccination</p>
                    <div class="flex items-center">
                      <h4 class="text-lg font-bold bg-gradient-to-r from-foreground to-foreground/80 bg-clip-text text-transparent">
                        {{ petDetails.vaccinated ? 'Up to date' : 'Needed' }}
                      </h4>
                      <span :class="['ml-2 h-2 w-2 rounded-full', petDetails.vaccinated ? 'bg-green-500' : 'bg-amber-500']"></span>
                    </div>
                  </div>
                </div>

                <!-- Spayed/Neutered -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white to-muted/30 dark:from-gray-800/50 dark:to-gray-800/20 p-5 shadow-sm border border-muted/20 hover:border-amber-500/20 hover:shadow-md transition-all duration-300">
                  <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-amber-500/5 dark:bg-amber-500/10 group-hover:bg-amber-500/10 dark:group-hover:bg-amber-500/20 transition-colors duration-300"></div>
                  <div class="relative z-10">
                    <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-100 to-amber-50 text-amber-600 dark:from-amber-900/30 dark:to-amber-800/20 dark:text-amber-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </div>
                    <p class="text-sm font-medium text-muted-foreground mb-1">Spayed/Neutered</p>
                    <h4 class="text-lg font-bold bg-gradient-to-r from-foreground to-foreground/80 bg-clip-text text-transparent">
                      {{ petDetails.spayedNeutered ? 'Yes' : 'No' }}
                    </h4>
                  </div>
                </div>

                <!-- With Kids -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white to-muted/30 dark:from-gray-800/50 dark:to-gray-800/20 p-5 shadow-sm border border-muted/20 hover:border-emerald-500/20 hover:shadow-md transition-all duration-300">
                  <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-500/5 dark:bg-emerald-500/10 group-hover:bg-emerald-500/10 dark:group-hover:bg-emerald-500/20 transition-colors duration-300"></div>
                  <div class="relative z-10">
                    <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-50 text-emerald-600 dark:from-emerald-900/30 dark:to-emerald-800/20 dark:text-emerald-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                      </svg>
                    </div>
                    <p class="text-sm font-medium text-muted-foreground mb-1">With Kids</p>
                    <div class="flex items-center">
                      <h4 class="text-lg font-bold bg-gradient-to-r from-foreground to-foreground/80 bg-clip-text text-transparent">
                        {{ petDetails.goodWithKids ? 'Friendly' : 'Not recommended' }}
                      </h4>
                      <span :class="['ml-2 h-2 w-2 rounded-full', petDetails.goodWithKids ? 'bg-green-500' : 'bg-amber-500']"></span>
                    </div>
                  </div>
                </div>

                <!-- With Other Pets -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-white to-muted/30 dark:from-gray-800/50 dark:to-gray-800/20 p-5 shadow-sm border border-muted/20 hover:border-purple-500/20 hover:shadow-md transition-all duration-300">
                  <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-purple-500/5 dark:bg-purple-500/10 group-hover:bg-purple-500/10 dark:group-hover:bg-purple-500/20 transition-colors duration-300"></div>
                  <div class="relative z-10">
                    <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-100 to-purple-50 text-purple-600 dark:from-purple-900/30 dark:to-purple-800/20 dark:text-purple-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                      </svg>
                    </div>
                    <p class="text-sm font-medium text-muted-foreground mb-1">With Other Pets</p>
                    <div class="flex items-center">
                      <h4 class="text-lg font-bold bg-gradient-to-r from-foreground to-foreground/80 bg-clip-text text-transparent">
                        {{ petDetails.goodWithPets ? 'Gets along' : 'Prefers solo' }}
                      </h4>
                      <span :class="['ml-2 h-2 w-2 rounded-full', petDetails.goodWithPets ? 'bg-green-500' : 'bg-amber-500']"></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Enhanced About Section -->
              <div class="relative overflow-hidden rounded-2xl border border-muted/20 bg-gradient-to-br from-white to-muted/10 shadow-sm backdrop-blur-sm transition-all duration-300 hover:shadow-md dark:from-gray-800/50 dark:to-gray-800/10">
                <!-- Decorative elements -->
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/5 blur-2xl"></div>
                <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-blue-500/5 blur-2xl"></div>
                <div class="absolute -right-10 top-1/2 h-40 w-40 -translate-y-1/2 rounded-full bg-purple-500/5 blur-xl"></div>

                <div class="relative z-10 p-8">
                  <!-- Header with animated icon -->
                  <div class="group mb-8 flex items-center gap-4">
                    <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-500 p-2 text-white shadow-lg transition-all duration-300 group-hover:rotate-6 group-hover:scale-110">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                      </svg>
                      <div class="absolute -inset-1 rounded-2xl bg-primary/20 opacity-0 blur-md transition-all duration-300 group-hover:opacity-100"></div>
                    </div>
                    <div>
                      <h3 class="text-2xl font-bold tracking-tight bg-gradient-to-r from-foreground to-foreground/80 bg-clip-text text-transparent sm:text-3xl">
                        Meet {{ petDetails.name.split(' ')[0] }}! 🐾
                      </h3>
                      <div class="flex flex-wrap items-center gap-3 mt-2">
                        <div class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30 rounded-full text-blue-600 dark:text-blue-300 text-sm font-medium">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.5 12c0-1.92-1.02-3.6-2.54-4.52l.02-.02-1.17-1.14-1.11 1.14c-1.05-.32-2.14-.49-3.2-.49-1.06 0-2.15.17-3.2.49L7.29 6.32l-1.17 1.14.02.02C4.52 8.4 3.5 10.08 3.5 12c0 4.14 3.36 7.5 7.5 7.5s7.5-3.36 7.5-7.5z" />
                            <path d="M12 15c1.93 0 3.5-1.57 3.5-3.5S13.93 8 12 8 8.5 9.57 8.5 11.5 10.07 15 12 15zm0-5c.83 0 1.5.67 1.5 1.5S12.83 13 12 13s-1.5-.67-1.5-1.5S11.17 10 12 10z" />
                          </svg>
                          <span class="whitespace-nowrap">{{ petDetails.breed }}</span>
                        </div>

                        <div class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 dark:bg-amber-900/20 rounded-full text-amber-600 dark:text-amber-400 text-sm font-medium">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3h-1V2c0-.55-.45-1-1-1s-1 .45-1 1v1H8V2c0-.55-.45-1-1-1s-1 .45-1 1v1.18C3.6 4.58 2 6.69 2 9v8c0 1.65 1.35 3 3 3h14c1.65 0 3-1.35 3-3V9c0-2.31-1.6-4.42-3.9-4.82V3zM19 19H5c-.55 0-1-.45-1-1v-9c0-2.21 1.79-4 4-4h8c2.21 0 4 1.79 4 4v9c0 .55-.45 1-1 1z" />
                            <path d="M12 12c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" />
                          </svg>
                          <span class="whitespace-nowrap">{{ petDetails.age }} {{ petDetails.age === 1 ? 'year' : 'years' }} old</span>
                        </div>

                        <div v-if="petDetails.gender" class="flex items-center gap-1.5 px-3 py-1.5 bg-pink-50 dark:bg-pink-900/20 rounded-full text-pink-600 dark:text-pink-400 text-sm font-medium">
                          <svg v-if="petDetails.gender.toLowerCase() === 'male'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9.5 11c1.93 0 3.5 1.57 3.5 3.5S11.43 18 9.5 18 6 16.43 6 14.5 7.57 11 9.5 11zm0-2C6.46 9 4 11.46 4 14.5S6.46 20 9.5 20s5.5-2.46 5.5-5.5c0-1.16-.36-2.23-.97-3.12L18 7.42V10h2V4h-6v2h2.58l-3.97 3.97C13.18 9.09 11.41 9 9.5 9z" />
                          </svg>
                          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14.5 11c1.93 0 3.5 1.57 3.5 3.5S16.43 18 14.5 18 11 16.43 11 14.5s1.57-3.5 3.5-3.5zm0-2c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm-11-.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v.5h-3v-.5zm0 2h3v8h-3v-8z" />
                          </svg>
                          <span class="whitespace-nowrap">{{ petDetails.gender }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Description with animated border -->
                  <div class="group relative mb-8 overflow-hidden rounded-xl border border-muted/20 bg-white/50 p-6 transition-all duration-300 hover:shadow-sm dark:bg-gray-800/30">
                    <div class="absolute inset-0 -z-10 bg-[radial-gradient(100%_100%_at_0%_0%,#3b82f60a,transparent)] opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                    <div class="relative z-10">
                      <p class="text-muted-foreground leading-relaxed">
                        {{ petDetails.description }}
                      </p>
                    </div>
                    <div class="absolute -bottom-px -left-px -right-px h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                  </div>

                  <!-- Personality Traits with hover effects -->
                  <div class="mb-8">
                    <h4 class="mb-4 text-sm font-medium uppercase tracking-wider text-muted-foreground flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                      </svg>
                      Personality Traits
                    </h4>
                    <div class="flex flex-wrap gap-2">
                      <span v-for="(trait, index) in ['Friendly', 'Playful', 'Affectionate', 'Curious', 'Gentle', 'Intelligent']"
                            :key="index"
                            class="group/trait inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-all duration-200 hover:scale-105 hover:shadow-sm"
                            :class="{
                              'border-primary/20 bg-primary/5 text-primary hover:bg-primary/10': index % 4 === 0,
                              'border-blue-500/20 bg-blue-500/5 text-blue-600 hover:bg-blue-500/10 dark:text-blue-400': index % 4 === 1,
                              'border-emerald-500/20 bg-emerald-500/5 text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400': index % 4 === 2,
                              'border-amber-500/20 bg-amber-500/5 text-amber-600 hover:bg-amber-500/10 dark:text-amber-400': index % 4 === 3,
                            }">
                        <span class="relative flex h-2 w-2">
                          <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" :class="{
                            'bg-primary/80': index % 4 === 0,
                            'bg-blue-500/80': index % 4 === 1,
                            'bg-emerald-500/80': index % 4 === 2,
                            'bg-amber-500/80': index % 4 === 3,
                          }"></span>
                          <span class="relative inline-flex h-2 w-2 rounded-full" :class="{
                            'bg-primary': index % 4 === 0,
                            'bg-blue-500': index % 4 === 1,
                            'bg-emerald-500': index % 4 === 2,
                            'bg-amber-500': index % 4 === 3,
                          }"></span>
                        </span>
                        {{ trait }}
                      </span>
                    </div>
                  </div>

                  <!-- Additional Details -->
                  <div class="rounded-xl border border-muted/20 bg-white/50 p-6 backdrop-blur-sm transition-all duration-300 hover:shadow-sm dark:bg-gray-800/30">
                    <h4 class="mb-4 text-sm font-medium uppercase tracking-wider text-muted-foreground flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      Additional Details
                      Quick Info
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="flex items-start gap-3">
                        <div class="p-2 rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 mt-0.5">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                          </svg>
                        </div>
                        <div>
                          <p class="text-sm font-medium text-muted-foreground">Ideal Home</p>
                          <p class="font-medium text-foreground">House or Apartment</p>
                          <p class="text-xs text-muted-foreground mt-0.5">With loving family</p>
                        </div>
                      </div>
                      <div class="flex items-start gap-3">
                        <div class="p-2 rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 mt-0.5">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                        <div>
                          <p class="text-sm font-medium text-muted-foreground">Available From</p>
                          <p class="font-medium text-foreground">Immediately</p>
                          <p class="text-xs text-muted-foreground mt-0.5">Ready for a new home</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Health & Care -->
              <div class="mt-6 bg-blue-50/50 rounded-xl p-6 border border-blue-100">
                <div class="flex items-center gap-3 mb-4">
                  <div class="p-2 rounded-lg bg-blue-100 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <h3 class="text-lg font-semibold text-blue-900">Health & Care</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex items-center gap-3">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <span class="text-sm">Vaccinations up to date</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <span class="text-sm">Regular vet check-ups</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <span class="text-sm">Flea & worm treatment</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                    <span class="text-sm">Microchipped</span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Comments Section -->
          <div class="mb-8 bg-background rounded-xl p-6 shadow-sm border border-muted/20">
            <div class="flex justify-between items-center mb-6">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-primary/10 text-primary rounded-lg">
                  <MessageSquare class="h-5 w-5" />
                </div>
                <h2 class="text-2xl font-bold text-foreground">Comments</h2>
              </div>
              <span class="text-muted-foreground text-sm bg-muted/20 px-3 py-1 rounded-full">{{ comments.length }} {{ comments.length === 1 ? 'comment' : 'comments' }}</span>
            </div>

            <!-- New Comment -->
            <div class="mb-8 bg-muted/20 rounded-xl p-4 border border-muted/30">
              <div class="flex gap-3">
                <Avatar class="h-10 w-10 border-2 border-white dark:border-gray-800 shadow-sm">
                  <AvatarImage src="https://randomuser.me/api/portraits/men/3.jpg" />
                  <AvatarFallback class="bg-gradient-to-br from-primary to-blue-500 text-white font-medium">CU</AvatarFallback>
                </Avatar>
                <div class="flex-1">
                  <div class="relative">
                    <Input
                      v-model="newComment"
                      placeholder="Share your thoughts about this pet..."
                      class="pr-12 bg-background hover:bg-muted/30 focus:bg-background transition-colors duration-200"
                      @keyup.enter="submitComment"
                    />
                    <Button
                      variant="ghost"
                      size="icon"
                      class="absolute right-1 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-primary h-8 w-8"
                      :disabled="!newComment.trim()"
                      @click="submitComment"
                    >
                      <Send class="h-4 w-4" />
                    </Button>
                  </div>
                  <p class="text-xs text-muted-foreground mt-2 ml-1">
                    Press Enter to post your comment
                  </p>
                </div>
              </div>
            </div>

            <!-- Comments List -->
            <div class="space-y-6">
              <div v-for="comment in comments" :key="comment.id" class="space-y-4">
                <!-- Parent Comment -->
                <div class="flex gap-3">
                  <Avatar class="h-10 w-10 flex-shrink-0">
                    <AvatarImage :src="comment.user.avatar" />
                    <AvatarFallback>{{ comment.user.name.charAt(0) }}</AvatarFallback>
                  </Avatar>
                  <div class="flex-1">
                    <div class="bg-muted/50 rounded-2xl p-4">
                      <div class="flex justify-between items-start mb-1">
                        <div class="flex items-center gap-1">
                          <span class="font-semibold">{{ comment.user.name }}</span>
                          <span v-if="comment.user.verified" class="text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 011.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 011.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                          </span>
                        </div>
                        <div class="flex items-center gap-2">
                          <span class="text-xs text-muted-foreground">{{ comment.timestamp }}</span>
                          <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                              <Button variant="ghost" size="icon" class="h-6 w-6">
                                <MoreHorizontal class="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                              <DropdownMenuItem
                                v-if="comment.user.id === currentUser?.id"
                                @click="onCommentAction('edit', comment)"
                              >
                                <Edit class="mr-2 h-4 w-4" />
                                <span>Edit</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                v-if="comment.user.id === currentUser?.id"
                                @click="onCommentAction('delete', comment)"
                                class="text-red-600"
                              >
                                <Trash2 class="mr-2 h-4 w-4" />
                                <span>Delete</span>
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                v-else
                                @click="$emit('report-content', 'comment', comment.id)"
                              >
                                <Flag class="mr-2 h-4 w-4" />
                                <span>Report</span>
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      </div>
                      <p class="text-sm">{{ comment.content }}</p>
                      <div class="flex items-center gap-4 mt-2 text-xs">
                        <button
                          class="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                          @click="handleLike(comment)"
                        >
                          <ThumbsUp class="h-3.5 w-3.5" :class="{ 'text-primary fill-primary': comment.isLiked }" />
                          <span>{{ comment.likes }}</span>
                        </button>
                        <button
                          class="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                          @click="handleReply(comment)"
                        >
                          <Reply class="h-3.5 w-3.5" />
                          <span>Reply</span>
                        </button>
                        <button
                            class="text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1"
                            @click="openReportDialog"
                        >
                          <Flag class="h-3.5 w-3.5" />
                          <span>Report</span>
                        </button>
                      </div>
                    </div>

                    <!-- Reply Input -->
                    <div v-if="activeReplyId === comment.id" class="mt-4 pl-4 border-l-2 border-muted">
                      <div class="flex gap-3">
                        <Avatar class="h-8 w-8 flex-shrink-0">
                          <AvatarImage src="https://randomuser.me/api/portraits/men/3.jpg" />
                          <AvatarFallback>CU</AvatarFallback>
                        </Avatar>
                        <div class="flex-1">
                          <div class="relative">
                            <Input
                              v-model="replyContent"
                              :placeholder="`Replying to ${comment.user.name}...`"
                              class="pr-12"
                              @keyup.enter="submitReply(comment.id)"
                            />
                            <Button
                              variant="ghost"
                              size="icon"
                              class="absolute right-1 top-1/2 -translate-y-1/2 h-6 w-6"
                              :disabled="!replyContent.trim()"
                              @click="submitReply(comment.id)"
                            >
                              <Send class="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Replies -->
                    <div v-if="comment.replies.length > 0" class="mt-4 space-y-4 pl-4 border-l-2 border-muted/50">
                      <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-3">
                        <Avatar class="h-8 w-8 flex-shrink-0">
                          <AvatarImage :src="reply.user.avatar" />
                          <AvatarFallback>{{ reply.user.name.charAt(0) }}</AvatarFallback>
                        </Avatar>
                        <div class="flex-1">
                          <div class="bg-muted/30 rounded-2xl p-3">
                            <div class="flex justify-between items-start mb-1">
                              <div class="flex items-center gap-1">
                                <span class="font-medium text-sm">{{ reply.user.name }}</span>
                                <span v-if="reply.user.verified" class="text-primary">
                                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 011.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 011.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                  </svg>
                                </span>
                              </div>
                              <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">{{ reply.timestamp }}</span>
                                <Button variant="ghost" size="icon" class="h-6 w-6">
                                  <MoreHorizontal class="h-3.5 w-3.5" />
                                </Button>
                              </div>
                            </div>
                            <p class="text-sm">{{ reply.content }}</p>
                            <div class="flex items-center gap-4 mt-1 text-xs">
                              <button
                                class="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                                @click="handleLike(reply)"
                              >
                                <ThumbsUp class="h-3.5 w-3.5" :class="{ 'text-primary fill-primary': reply.isLiked }" />
                                <span>{{ reply.likes }}</span>
                              </button>
                              <button
                                class="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                                @click="handleReply(comment)"
                              >
                                <Reply class="h-3.5 w-3.5" />
                                <span>Reply</span>
                              </button>
                              <button class="text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1">
                                <Flag class="h-3.5 w-3.5" />
                                <span>Report</span>
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Sidebar -->
        <div class="lg:w-1/3">
          <div class="sticky top-4 space-y-6">
            <!-- Enhanced Owner Card -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-300">
              <div class="relative h-2 bg-gradient-to-r from-primary to-blue-400"></div>

              <div class="p-6">
                <div class="flex justify-between items-start mb-6">
                  <div class="flex items-center gap-4">
                    <div class="relative">
                      <div class="absolute -right-1 -top-1 h-4 w-4 rounded-full bg-green-400 border-2 border-white dark:border-gray-900 z-10">
                        <div class="absolute inset-0.5 bg-green-400 rounded-full animate-ping"></div>
                      </div>
                      <Avatar class="h-16 w-16 border-2 border-white dark:border-gray-800 shadow-md">
                        <AvatarImage :src="owner.avatar" />
                        <AvatarFallback class="bg-gradient-to-br from-primary to-blue-500 text-white font-semibold text-xl">
                          {{ owner.name.split(' ').map(n => n[0]).join('') }}
                        </AvatarFallback>
                      </Avatar>
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <h3 class="text-xl font-bold bg-gradient-to-r from-foreground to-foreground/80 bg-clip-text text-transparent">
                          {{ owner.name }}
                        </h3>
                        <span v-if="owner.verified" class="text-blue-500" title="Verified Owner">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 011.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 011.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                          </svg>
                        </span>
                      </div>

                      <div class="mt-1 flex items-center text-sm text-muted-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                        </svg>
                        {{ owner.location }}
                      </div>

                      <div class="mt-1 flex items-center text-sm text-muted-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                        </svg>
                        Member since {{ owner.memberSince }}
                      </div>

                      <div class="mt-2 flex items-center">
                        <div class="flex text-amber-400">
                          <template v-for="i in 5" :key="i">
                            <svg
                              :class="['h-4 w-4', i <= Math.floor(owner.rating) ? 'fill-current' : 'fill-amber-200 dark:fill-amber-900']"
                              xmlns="http://www.w3.org/2000/svg"
                              viewBox="0 0 20 20"
                              fill="currentColor"
                            >
                              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                          </template>
                        </div>
                        <span class="ml-2 text-sm font-medium text-foreground/80">{{ owner.rating }}/5.0</span>
                        <span class="mx-2 text-muted-foreground">•</span>
                        <span class="text-sm text-muted-foreground">12 reviews</span>
                      </div>
                    </div>
                  </div>

                  <button class="text-muted-foreground hover:text-foreground transition-colors p-1 -mt-1 -mr-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                    </svg>
                  </button>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-6">
                  <Button class="h-12 rounded-xl bg-gradient-to-r from-primary to-blue-500 hover:from-primary/90 hover:to-blue-500/90 transition-all duration-200 transform hover:-translate-y-0.5 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                    </svg>
                    Call Now
                  </Button>
                  <Button
                    variant="outline"
                    class="h-12 rounded-xl border-2 border-muted-foreground/20 hover:border-primary/50 transition-all duration-200 group"
                    @click="showMessageDialog = true"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500 group-hover:text-blue-600 transition-colors" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clip-rule="evenodd" />
                    </svg>
                    Message
                  </Button>
                </div>

                <div class="mt-6 pt-5 border-t border-muted-foreground/10">
                  <div class="flex items-center justify-between text-sm mb-2">
                    <span class="text-muted-foreground flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      Response rate
                    </span>
                    <span class="font-semibold text-foreground">98%</span>
                  </div>
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V6z" clip-rule="evenodd" />
                      </svg>
                      Response time
                    </span>
                    <span class="font-semibold text-foreground">Within an hour</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Safety Tips -->
            <Card>
              <CardHeader>
                <CardTitle class="text-xl">Safety Tips</CardTitle>
              </CardHeader>
              <CardContent>
                <ul class="space-y-3">
                  <li class="flex items-start gap-3">
                    <div class="bg-primary/10 p-1 rounded-full mt-0.5">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <span class="text-sm">Meet in a public place</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <div class="bg-primary/10 p-1 rounded-full mt-0.5">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <span class="text-sm">Never send money in advance</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <div class="bg-primary/10 p-1 rounded-full mt-0.5">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <span class="text-sm">Check the pet's health records</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <div class="bg-primary/10 p-1 rounded-full mt-0.5">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <span class="text-sm">Ask for ownership documents</span>
                  </li>
                </ul>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
      <!-- Report Dialog -->
      <ReportDialog
          :is-open="reportDialogOpen"
          :content-type="reportContentType"
          :content-id="reportContentId"
          @close="closeReportDialog"
          @submit="handleReportSubmit"
      />

    <!-- Quick Message Dialog -->
    <Dialog v-model:open="showMessageDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Message {{ owner.name }}</DialogTitle>
          <DialogDescription>
            Send a message to the pet owner about this listing.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-foreground">Your Message</label>
            <textarea
              v-model="messageContent"
              class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 min-h-[120px]"
              placeholder="Type your message here..."
            ></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="showMessageDialog = false">Cancel</Button>
          <Button
            @click="sendMessage"
            :disabled="!messageContent.trim()"
            class="bg-primary text-primary-foreground hover:bg-primary/90"
          >
            Send Message
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  </MainLayout>
</template>

<style scoped>
/* Custom scrollbar for thumbnails */
::-webkit-scrollbar {
  height: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
