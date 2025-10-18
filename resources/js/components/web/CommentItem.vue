<template>
  <div
    class="comment-item group relative"
    :class="{ 'ml-8 mt-3': isReply, 'border-l-2 border-gray-200 dark:border-gray-700 pl-3': isReply && !isNestedReply, 'ml-6': isNestedReply }"
  >
    <div class="flex items-start gap-3">
      <div
        class="rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 font-medium"
        :class="{ 'h-10 w-10': !isReply, 'h-8 w-8': isReply }"
      >
        {{ comment.user.name.charAt(0).toUpperCase() }}
      </div>

      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <p class="font-medium text-sm">{{ comment.user.name }}</p>
            <span class="text-xs text-gray-500 dark:text-gray-400">• {{ formatDate(comment.created_at) }}</span>
          </div>

          <!-- Comment Options Dropdown -->
          <DropdownMenu>
            <DropdownMenuTrigger class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
              <MoreHorizontal class="h-4 w-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem @click="$emit('report-content', 'comment', comment.id)">
                <Flag class="mr-2 h-4 w-4" />
                <span>Report</span>
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="comment.user.id === currentUserId"
                @click="$emit('edit-comment', comment)"
              >
                <Edit2 class="mr-2 h-4 w-4" />
                <span>Edit</span>
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="comment.user.id === currentUserId"
                @click="$emit('delete-comment', comment.id)"
                class="text-red-500 focus:text-red-500"
              >
                <Trash2 class="mr-2 h-4 w-4" />
                <span>Delete</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <p class="mt-1 text-gray-800 dark:text-gray-200">{{ comment.content }}</p>

        <!-- Comment Actions -->
        <div class="mt-1.5 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
          <button @click="$emit('toggle-like', comment)" class="flex items-center gap-1 hover:text-blue-500 dark:hover:text-blue-400">
            <Heart :class="['h-3.5 w-3.5', comment.isLiked ? 'fill-red-500 text-red-500' : '']" />
            <span class="text-gray-500 dark:text-gray-400">{{ comment.likes || 0 }}</span>
          </button>
          <button @click="$emit('toggle-reply', comment.id)" class="hover:text-blue-500 dark:hover:text-blue-400">
            Reply
          </button>
        </div>

        <!-- Reply Form -->
        <div v-if="activeReply === comment.id" class="ml-12 mt-2">
          <form @submit.prevent="$emit('add-reply', comment, localReplyText)" class="flex gap-2">
            <Input
              v-model="localReplyText"
              placeholder="Write a reply..."
              class="flex-1 text-sm h-9 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
              required
            />
            <Button
              type="submit"
              size="sm"
              class="h-9 bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:opacity-90 text-white"
              :disabled="!localReplyText.trim()"
              :class="{ 'opacity-75 cursor-not-allowed': !localReplyText.trim() }"
            >
              <span>Reply</span>
            </Button>
          </form>
        </div>

        <!-- Nested Replies -->
        <div v-if="comment.replies && comment.replies.length > 0" class="mt-3 space-y-3">
          <CommentItem
            v-for="reply in comment.replies"
            :key="reply.id"
            :comment="reply"
            :current-user-id="currentUserId"
            :is-reply="true"
            :is-nested-reply="isReply"
            :active-reply="activeReply"
            :reply-text="replyText"
            @toggle-reply="$emit('toggle-reply', $event)"
            @add-reply="$emit('add-reply', comment, $event)"
            @edit-comment="$emit('edit-comment', $event)"
            @delete-comment="$emit('delete-comment', $event)"
            @report-content="$emit('report-content', $event)"
            @toggle-like="$emit('toggle-like', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  MoreHorizontal,
  Edit2,
  Trash2,
  Flag,
  Heart
} from 'lucide-vue-next';

// Import the component itself for recursive rendering
import CommentItem from '@/components/web/CommentItem.vue';

// Create a local ref for replyText to avoid mutating props
const localReplyText = ref('');

// Define emits
defineEmits(['toggle-reply', 'add-reply', 'edit-comment', 'delete-comment', 'report-content', 'toggle-like']);

// Define props for access in the script
const props = defineProps({
  comment: {
    type: Object,
    required: true
  },
  currentUserId: {
    type: [String, Number],
    default: null
  },
  isReply: {
    type: Boolean,
    default: false
  },
  isNestedReply: {
    type: Boolean,
    default: false
  },
  activeReply: {
    type: [String, Number, null],
    default: null
  },
  replyText: {
    type: String,
    default: ''
  }
});

// Watch for changes in the replyText prop
watch(() => props.replyText, (newValue) => {
  localReplyText.value = newValue;
});

const formatDate = (dateString: string) => {
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
</script>
