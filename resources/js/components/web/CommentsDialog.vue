<template>
  <Dialog>
    <DialogTrigger as-child>
      <button class="group flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition-colors hover:bg-gray-100 hover:text-blue-500">
        <MessageSquare class="h-5 w-5 group-hover:fill-blue-500 group-hover:text-blue-500" />
        <span class="text-sm font-medium">{{ commentsCount || 0 }}</span>
      </button>
    </DialogTrigger>

    <DialogContent class="sm:max-w-[600px] max-h-[80vh] flex flex-col">
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

        <div v-else-if="comments.length === 0" class="py-8 text-center text-gray-500">
          No comments yet. Be the first to comment!
        </div>

        <div v-else class="space-y-6">
          <div v-for="comment in comments" :key="comment.id" class="group relative">
            <div class="flex items-start gap-3">
              <div class="h-10 w-10 flex-shrink-0 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium">
                {{ comment.user.name.charAt(0).toUpperCase() }}
              </div>
              
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-medium text-sm">{{ comment.user.name }}</p>
                  <span class="text-xs text-gray-500">• {{ formatDate(comment.created_at) }}</span>
                </div>
                
                <p class="mt-1 text-gray-800">{{ comment.content }}</p>
                
                <!-- Comment Actions -->
                <div class="mt-1.5 flex items-center gap-4 text-xs text-gray-500">
                  <button @click="toggleLike(comment)" class="flex items-center gap-1 hover:text-blue-500">
                    <Heart :class="['h-3.5 w-3.5', comment.isLiked ? 'fill-red-500 text-red-500' : '']" />
                    <span>{{ comment.likes || 0 }}</span>
                  </button>
                  <button @click="toggleReply(comment.id)" class="hover:text-blue-500">
                    Reply
                  </button>
                </div>
                
                <!-- Reply Form -->
                <div v-if="activeReply === comment.id" class="mt-3 pl-4 border-l-2 border-gray-200">
                  <div class="flex gap-2">
                    <Input
                      v-model="replyText"
                      placeholder="Write a reply..."
                      class="flex-1 text-sm h-9"
                      @keyup.enter="addReply(comment)"
                    />
                    <Button size="sm" @click="addReply(comment)">
                      <SendHorizontal class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
                
                <!-- Replies -->
                <div v-if="comment.replies?.length > 0" class="mt-3 space-y-4 pl-4 border-l-2 border-gray-100">
                  <div v-for="reply in comment.replies" :key="reply.id" class="group relative">
                    <div class="flex items-start gap-3">
                      <div class="h-8 w-8 flex-shrink-0 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 text-xs font-medium">
                        {{ reply.user.name.charAt(0).toUpperCase() }}
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                          <p class="text-sm font-medium">{{ reply.user.name }}</p>
                          <span class="text-xs text-gray-500">• {{ formatDate(reply.created_at) }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-800">{{ reply.content }}</p>
                      </div>
                      <DropdownMenu>
                        <DropdownMenuTrigger class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity p-1 -mr-1">
                          <MoreHorizontal class="h-4 w-4 text-gray-400 hover:text-gray-600" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-40">
                          <DropdownMenuItem v-if="reply.user.id === currentUserId" @click="editReply(reply)">
                            <Edit2 class="mr-2 h-4 w-4" />
                            <span>Edit</span>
                          </DropdownMenuItem>
                          <DropdownMenuItem v-if="reply.user.id === currentUserId" @click="deleteReply(comment, reply.id)" class="text-red-600">
                            <Trash2 class="mr-2 h-4 w-4" />
                            <span>Delete</span>
                          </DropdownMenuItem>
                          <DropdownMenuItem v-if="reply.user.id !== currentUserId" @click="reportContent('reply', reply.id)">
                            <Flag class="mr-2 h-4 w-4" />
                            <span>Report</span>
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Comment Options -->
              <DropdownMenu>
                <DropdownMenuTrigger class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity p-1 -mr-1">
                  <MoreHorizontal class="h-4 w-4 text-gray-400 hover:text-gray-600" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-40">
                  <DropdownMenuItem v-if="comment.user.id === currentUserId" @click="editComment(comment)">
                    <Edit2 class="mr-2 h-4 w-4" />
                    <span>Edit</span>
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="comment.user.id === currentUserId" @click="deleteComment(comment.id)" class="text-red-600">
                    <Trash2 class="mr-2 h-4 w-4" />
                    <span>Delete</span>
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="comment.user.id !== currentUserId" @click="reportContent('comment', comment.id)">
                    <Flag class="mr-2 h-4 w-4" />
                    <span>Report</span>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Comment Form -->
      <form @submit.prevent="addComment" class="mt-4 border-t pt-4">
        <div class="flex gap-2">
          <Input
            v-model="newComment"
            placeholder="Write a comment..."
            class="flex-1"
            required
          />
          <Button type="submit" :disabled="isSubmitting">
            <SendHorizontal class="h-4 w-4 mr-1" />
            {{ isSubmitting ? 'Posting...' : 'Post' }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { 
  MessageSquare, 
  Trash2, 
  SendHorizontal, 
  MoreHorizontal, 
  Edit2, 
  Flag,
  Heart
} from 'lucide-vue-next';
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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
  },
});
const emit = defineEmits(['comment-added', 'comment-deleted']);

const comments = ref([
  {
    id: 1,
    content: 'This pet is absolutely adorable! How old is it?',
    created_at: new Date(Date.now() - 3600000 * 2).toISOString(),
    likes: 3,
    isLiked: false,
    user: {
      id: 2,
      name: 'Sarah Johnson',
      avatar: null
    },
    replies: [
      {
        id: 101,
        content: 'Thank you! He\'s about 2 years old according to the shelter.',
        created_at: new Date(Date.now() - 3600000).toISOString(),
        user: {
          id: 1,
          name: 'Alex Chen',
          avatar: null
        }
      },
      {
        id: 102,
        content: 'He looks so playful!',
        created_at: new Date(Date.now() - 1800000).toISOString(),
        user: {
          id: 3,
          name: 'Mike Peterson',
          avatar: null
        }
      }
    ]
  },
  {
    id: 2,
    content: 'What breed is this? So cute!',
    created_at: new Date(Date.now() - 86400000).toISOString(),
    likes: 1,
    isLiked: true,
    user: {
      id: 4,
      name: 'Emma Wilson',
      avatar: null
    },
    replies: []
  }
]);

const newComment = ref('');
const replyText = ref('');
const activeReply = ref(null);
const isLoading = ref(false);
const isSubmitting = ref(false);

const fetchComments = async () => {
  try {
    isLoading.value = true;
    const response = await axios.get(`/api/pets/${props.petId}/comments`);
    comments.value = response.data.data;
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
  }
};

// Toggle like on a comment
const toggleLike = (comment) => {
  comment.isLiked = !comment.isLiked;
  comment.likes = comment.isLiked ? (comment.likes || 0) + 1 : Math.max(0, (comment.likes || 1) - 1);
};

// Toggle reply input
const toggleReply = (commentId) => {
  activeReply.value = activeReply.value === commentId ? null : commentId;
  if (activeReply.value === commentId) {
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
      id: currentUserId.value,
      name: 'Current User', // This would come from your auth state
      avatar: null
    }
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

// Edit a reply
const editReply = (reply) => {
  const newContent = prompt('Edit your reply:', reply.content);
  if (newContent !== null && newContent.trim() !== '') {
    reply.content = newContent.trim();
  }
};

// Delete a reply
const deleteReply = (comment, replyId) => {
  if (confirm('Are you sure you want to delete this reply?')) {
    comment.replies = comment.replies.filter(reply => reply.id !== replyId);
  }
};

// Report content
const reportContent = (type, id) => {
  const reason = prompt('Please specify the reason for reporting:');
  if (reason) {
    console.log(`Reported ${type} ${id} for:`, reason);
    // In a real app, you would send this to your backend
    alert('Thank you for your report. We will review it shortly.');
  }
};

const formatDate = (dateString) => {
  const now = new Date();
  const date = new Date(dateString);
  const diffInSeconds = Math.floor((now - date) / 1000);
  
  if (diffInSeconds < 60) return 'Just now';
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
  
  // For older dates, show the actual date
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

// Fetch comments when dialog is opened
const onOpenChange = (isOpen) => {
  if (isOpen) {
    fetchComments();
  }
};
</script>
