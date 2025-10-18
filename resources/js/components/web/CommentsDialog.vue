<template>
  <Dialog>
    <DialogTrigger as-child>
      <button class="group flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-blue-500">
        <MessageSquare class="h-5 w-5 group-hover:fill-blue-500 group-hover:text-blue-500" />
        <span class="text-sm font-medium">{{ commentsCount || 0 }}</span>
      </button>
    </DialogTrigger>

    <DialogContent class="sm:max-w-[600px] max-h-[80vh] flex flex-col bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
      <DialogHeader>
        <DialogTitle class="text-xl">Comments for {{ petName }}</DialogTitle>
        <DialogDescription>
          Share your thoughts and questions about {{ petName }}.
        </DialogDescription>
      </DialogHeader>

      <!-- Comments List -->
      <div class="flex-1 overflow-y-auto py-4 pr-2 -mr-2">
        <div v-if="isLoading" class="flex justify-center py-8">
          <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-500"></div>
        </div>

        <div v-else-if="comments.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
          No comments yet. Be the first to comment!
        </div>

        <div v-else class="space-y-6">
          <!-- Comment Component (recursive) -->
          <div v-for="comment in comments" :key="comment.id" class="space-y-3">
            <CommentItem
              :comment="comment"
              :current-user-id="currentUserId"
              :active-reply="activeReply"
              @toggle-reply="toggleReply"
              @add-reply="addReply"
              @edit-comment="editComment"
              @delete-comment="deleteComment"
              @report-content="openReportDialog"
              @toggle-like="toggleLike"
            />
          </div>
        </div>
      </div>

      <!-- Add Comment Form -->
      <form @submit.prevent="addComment" class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
        <div class="flex gap-2">
          <Input
            v-model="newComment"
            placeholder="Write a comment..."
            class="flex-1"
            required
          />
          <Button
            type="submit"
            size="sm"
            class="h-9 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:opacity-90 text-white"
            :disabled="!newComment.trim()"
            :class="{ 'opacity-75 cursor-not-allowed': !newComment.trim() }"
          >
            <span>Comment</span>
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>

  <!-- Report Dialog -->
  <ReportDialog
    :is-open="reportDialogOpen"
    :content-type="reportContentType"
    :content-id="reportContentId"
    @close="closeReportDialog"
    @submit="handleReportSubmit"
  />

</template>


<script setup lang="ts">
import { ref } from 'vue';
import { MessageSquare } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import ReportDialog from '@/components/web/ReportDialog.vue';
import CommentItem from '@/components/web/CommentItem.vue';

import axios from 'axios';

const props = defineProps({
  petId: {
    type: [String, Number],
    required: true,
  },
  petName: {
    type: String,
    required: true,
  },
  commentsCount: {
    type: Number,
    default: 0,
  },
  currentUserId: {
    type: [String, Number],
    default: null,
  }
});

const emit = defineEmits(['comment-added', 'comment-deleted']);

const comments = ref([
  {
    id: 1,
    user: {
      name: 'Alex Johnson',
      id: 1,
      avatar: null
    },
    content: 'This pet is absolutely adorable! How old is it?',
    created_at: '2025-10-17T14:30:00Z',
    likes: 5,
    isLiked: false,
    replies: [
      {
        id: 101,
        content: 'Thank you! Max is about 2 years old according to the shelter records.',
        created_at: new Date(Date.now() - 3600000).toISOString(),
        user: {
          id: 2,
          name: 'Pet Owner',
          avatar: null
        },
        likes: 3,
        isLiked: false,
        replies: [
          {
            id: 1001,
            content: 'That\'s a great age! Is he already neutered?',
            created_at: new Date(Date.now() - 3000000).toISOString(),
            user: {
              id: 3,
              name: 'Mike Peterson',
              avatar: null
            },
            likes: 1,
            isLiked: false,
            replies: [
              {
                id: 10001,
                content: 'Yes, he was neutered before we got him from the shelter.',
                created_at: new Date(Date.now() - 2900000).toISOString(),
                user: {
                  id: 2,
                  name: 'Pet Owner',
                  avatar: null
                },
                likes: 2,
                isLiked: false
              }
            ]
          }
        ]
      },
      {
        id: 102,
        content: 'He looks so playful! Does he get along well with other dogs?',
        created_at: new Date(Date.now() - 1800000).toISOString(),
        user: {
          id: 3,
          name: 'Mike Peterson',
          avatar: null
        },
        likes: 2,
        isLiked: true,
        replies: []
      },
      {
        id: 103,
        content: 'Yes, he\'s great with other dogs! We have regular playdates at the local dog park.',
        created_at: new Date(Date.now() - 900000).toISOString(),
        user: {
          id: 2,
          name: 'Pet Owner',
          avatar: null
        },
        likes: 1,
        isLiked: false,
        replies: [
          {
            id: 1002,
            content: 'That\'s great to hear! What days do you usually go to the park?',
            created_at: new Date(Date.now() - 800000).toISOString(),
            user: {
              id: 4,
              name: 'Emma Wilson',
              avatar: null
            },
            likes: 0,
            isLiked: false
          }
        ]
      }
    ]
  },
  {
    id: 2,
    user: {
      name: 'Emma Wilson',
      id: 4,
      avatar: null
    },
    content: 'What breed is Max? His coat is absolutely stunning!',
    created_at: new Date(Date.now() - 86400000).toISOString(),
    likes: 8,
    isLiked: true,
    replies: [
      {
        id: 201,
        content: 'Max is a Golden Retriever mix! The shelter thinks he might have some Border Collie in him too.',
        created_at: new Date(Date.now() - 82800000).toISOString(),
        user: {
          id: 2,
          name: 'Pet Owner',
          avatar: null
        },
        likes: 4,
        isLiked: false,
        replies: [
          {
            id: 2001,
            content: 'I thought I saw some Border Collie in him! That explains his energy levels.',
            created_at: new Date(Date.now() - 82000000).toISOString(),
            user: {
              id: 5,
              name: 'Sarah Chen',
              avatar: null
            },
            likes: 2,
            isLiked: true
          }
        ]
      },
      {
        id: 202,
        content: 'That explains his beautiful golden coat and those intelligent eyes!',
        created_at: new Date(Date.now() - 81000000).toISOString(),
        user: {
          id: 5,
          name: 'Sarah Chen',
          avatar: null
        },
        likes: 2,
        isLiked: true,
        replies: []
      }
    ]
  },
  {
    id: 3,
    user: {
      name: 'James Wilson',
      id: 6,
      avatar: null
    },
    content: 'Is Max house-trained? Looking for a dog that\'s already trained.',
    created_at: new Date(Date.now() - 172800000).toISOString(),
    likes: 3,
    isLiked: false,
    replies: [
      {
        id: 301,
        content: 'Yes, Max is fully house-trained! He also knows basic commands like sit, stay, and paw.',
        created_at: new Date(Date.now() - 165600000).toISOString(),
        user: {
          id: 2,
          name: 'Pet Owner',
          avatar: null
        },
        likes: 5,
        isLiked: true,
        replies: [
          {
            id: 3001,
            content: 'That\'s impressive! How long did it take to train him?',
            created_at: new Date(Date.now() - 165000000).toISOString(),
            user: {
              id: 7,
              name: 'David Kim',
              avatar: null
            },
            likes: 1,
            isLiked: false,
            replies: [
              {
                id: 30001,
                content: 'He came to us knowing the basics, but we\'ve been working on more advanced commands for about 3 months now.',
                created_at: new Date(Date.now() - 164900000).toISOString(),
                user: {
                  id: 2,
                  name: 'Pet Owner',
                  avatar: null
                },
                likes: 3,
                isLiked: false
              }
            ]
          }
        ]
      },
      {
        id: 302,
        content: 'That\'s impressive! How is he with cats?',
        created_at: new Date(Date.now() - 158400000).toISOString(),
        user: {
          id: 6,
          name: 'James Wilson',
          avatar: null
        },
        likes: 1,
        isLiked: false,
        replies: []
      },
      {
        id: 303,
        content: 'He gets along well with cats! We have a resident cat and they\'re best friends.',
        created_at: new Date(Date.now() - 151200000).toISOString(),
        user: {
          id: 2,
          name: 'Pet Owner',
          avatar: null
        },
        likes: 3,
        isLiked: false,
        replies: []
      }
    ]
  }
]);

const newComment = ref('');
const replyText = ref('');
const activeReply = ref(null);
const isLoading = ref(false);
const isSubmitting = ref(false);

// Fetch comments from API
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const fetchComments = async () => {
  try {
    isLoading.value = true;
    // In a real implementation, this would fetch comments from the API
    // const response = await axios.get(`/api/pets/${props.petId}/comments`);
    // comments.value = response.data.data;

    // For demo purposes, we're using the sample data
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch (error) {
    console.error('Error fetching comments:', error);
  } finally {
    isLoading.value = false;
  }
};

const addComment = async () => {
  if (!newComment.value.trim()) return;

  try {
    isSubmitting.value = true;
    const response = await axios.post(`/api/pets/${props.petId}/comments`, {
      content: newComment.value.trim(),
    });

    comments.value.unshift(response.data.data);
    newComment.value = '';
    emit('comment-added', response.data.data);
  } catch (error) {
    console.error('Error adding comment:', error);

    // Create a dummy comment when the API call fails
    const dummyComment = {
      id: Date.now(),
      content: newComment.value.trim(),
      created_at: new Date().toISOString(),
      user: {
        id: props.currentUserId || 999,
        name: 'Current User',
        avatar: null
      },
      likes: 0,
      isLiked: false,
      replies: []
    };

    comments.value.unshift(dummyComment);
    newComment.value = '';
    emit('comment-added', dummyComment);
  } finally {
    isSubmitting.value = false;
  }
};

const deleteComment = async (commentId) => {
  if (!confirm('Are you sure you want to delete this comment?')) return;

  try {
    await axios.delete(`/api/comments/${commentId}`);
    comments.value = comments.value.filter(comment => comment.id !== commentId);
    emit('comment-deleted', commentId);
  } catch (error) {
    console.error('Error deleting comment:', error);

    // Proceed with the deletion even if the API call fails
    comments.value = comments.value.filter(comment => comment.id !== commentId);
    emit('comment-deleted', commentId);
  }
};

// Toggle like on a comment
const toggleLike = (comment) => {
  comment.isLiked = !comment.isLiked;
  comment.likes = comment.isLiked ? (comment.likes || 0) + 1 : Math.max(0, (comment.likes || 1) - 1);
};

// Toggle reply input
const toggleReply = (commentId) => {
  if (activeReply.value === commentId) {
    activeReply.value = null;
    replyText.value = '';
  } else {
    activeReply.value = commentId;
    replyText.value = '';
  }
};

// Add a new reply
const addReply = (comment) => {
  if (!replyText.value.trim()) return;

  const newReply = {
    id: Date.now(),
    content: replyText.value,
    created_at: new Date().toISOString(),
    user: {
      id: props.currentUserId || 999, // Use props.currentUserId or a default value
      name: 'Current User', // This would come from your auth state
      avatar: null
    },
    likes: 0,
    isLiked: false,
    replies: [] // Initialize empty replies array for nested replies
  };

  if (!comment.replies) {
    comment.replies = [];
  }

  comment.replies.push(newReply);
  replyText.value = '';
  activeReply.value = null;
};

// Edit a comment
const editComment = (comment) => {
  const newContent = prompt('Edit your comment:', comment.content);
  if (newContent !== null && newContent.trim() !== '') {
    comment.content = newContent.trim();
  }
};

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

// Note: formatDate function is defined in the CommentItem component
// and used there for displaying dates in the comment template
</script>
