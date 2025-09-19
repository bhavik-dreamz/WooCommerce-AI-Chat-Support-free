/**
 * WooCommerce AI Chat Widget JavaScript
 * Handles chat functionality, animations, and API communication
 */

(function($) {
    'use strict';

    class WCAIChatWidget {
        constructor() {
            this.isOpen = false;
            this.isMinimized = false;
            this.isTyping = false;
            this.messageHistory = [];
            this.currentConversationId = null;
            this.retryCount = 0;
            this.maxRetries = 3;
            
            this.init();
        }

        init() {
            if (!wcAiChat.settings.enabled) {
                return;
            }

            this.bindEvents();
            this.initializeWidget();
            this.loadConversationHistory();
            
            // Auto-open if configured
            if (wcAiChat.settings.autoOpen && !this.hasInteracted()) {
                setTimeout(() => this.openChat(), 2000);
            }
        }

        bindEvents() {
            // Toggle chat window
            $(document).on('click', '#wc-ai-chat-toggle', (e) => {
                e.preventDefault();
                this.toggleChat();
            });

            // Minimize/maximize chat
            $(document).on('click', '#wc-ai-chat-minimize', (e) => {
                e.preventDefault();
                this.minimizeChat();
            });

            // Close chat
            $(document).on('click', '#wc-ai-chat-close', (e) => {
                e.preventDefault();
                this.closeChat();
            });

            // Send message on form submit
            $(document).on('submit', '#wc-ai-chat-form', (e) => {
                e.preventDefault();
                this.sendMessage();
            });

            // Send message on Enter (but allow Shift+Enter for new lines)
            $(document).on('keydown', '#wc-ai-chat-input', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Auto-resize textarea
            $(document).on('input', '#wc-ai-chat-input', (e) => {
                this.autoResizeTextarea(e.target);
                this.toggleSendButton();
            });

            // Quick action buttons
            $(document).on('click', '.quick-action', (e) => {
                e.preventDefault();
                const message = $(e.target).data('message');
                this.sendQuickMessage(message);
            });

            // Action buttons in messages
            $(document).on('click', '.action-button', (e) => {
                e.preventDefault();
                const action = $(e.target).data('action');
                const data = $(e.target).data();
                this.handleActionButton(action, data);
            });

            // Product recommendations
            $(document).on('click', '.product-item', (e) => {
                e.preventDefault();
                const productId = $(e.target).closest('.product-item').data('product-id');
                this.handleProductClick(productId);
            });

            // Close chat when clicking outside (optional)
            $(document).on('click', (e) => {
                if (this.isOpen && !$(e.target).closest('#wc-ai-chat-widget').length) {
                    // Uncomment if you want to close on outside click
                    // this.closeChat();
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                // Escape to close chat
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeChat();
                }
            });

            // Page visibility change
            $(document).on('visibilitychange', () => {
                if (document.hidden) {
                    this.handlePageHidden();
                } else {
                    this.handlePageVisible();
                }
            });
        }

        initializeWidget() {
            // Add notification badge if there are unread messages
            this.updateNotificationBadge();
            
            // Set initial theme
            $('#wc-ai-chat-widget').addClass('theme-' + wcAiChat.settings.theme);
            
            // Focus input when chat opens
            this.bindChatOpenEvent();
        }

        toggleChat() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        }

        openChat() {
            this.isOpen = true;
            this.isMinimized = false;
            
            $('#wc-ai-chat-toggle').addClass('active');
            $('#wc-ai-chat-window').show();
            
            // Animate window appearance
            setTimeout(() => {
                $('#wc-ai-chat-window').addClass('visible');
                $('#wc-ai-chat-input').focus();
            }, 50);

            // Hide quick actions if conversation has started
            if (this.messageHistory.length > 1) {
                $('#wc-ai-chat-quick-actions').hide();
            }

            // Mark as interacted
            this.markAsInteracted();
            
            // Track analytics
            this.trackEvent('chat_opened');

            // Clear notification badge
            $('.notification-badge').hide();
        }

        closeChat() {
            this.isOpen = false;
            this.isMinimized = false;
            
            $('#wc-ai-chat-toggle').removeClass('active');
            $('#wc-ai-chat-window').removeClass('visible');
            
            setTimeout(() => {
                $('#wc-ai-chat-window').hide();
            }, 300);

            this.trackEvent('chat_closed');
        }

        minimizeChat() {
            this.isMinimized = true;
            this.isOpen = false;
            
            $('#wc-ai-chat-toggle').removeClass('active');
            $('#wc-ai-chat-window').removeClass('visible');
            
            setTimeout(() => {
                $('#wc-ai-chat-window').hide();
            }, 300);

            this.trackEvent('chat_minimized');
        }

        async sendMessage() {
            const input = $('#wc-ai-chat-input');
            const message = input.val().trim();
            
            if (!message || this.isTyping) {
                return;
            }

            // Clear input and hide quick actions
            input.val('');
            $('#wc-ai-chat-quick-actions').hide();
            this.autoResizeTextarea(input[0]);
            this.toggleSendButton();

            // Add user message to chat
            this.addMessage(message, 'user');

            // Show typing indicator
            this.showTypingIndicator();

            try {
                // Send message to API
                const response = await this.sendToAPI(message);
                
                this.hideTypingIndicator();
                
                if (response.success) {
                    // Add bot response
                    this.addMessage(response.response, 'bot', response.data);
                    
                    // Handle special actions
                    if (response.data && response.data.action) {
                        this.handleBotAction(response.data);
                    }
                    
                    this.retryCount = 0;
                } else {
                    throw new Error(response.message || 'Failed to get response');
                }
                
            } catch (error) {
                console.error('Chat error:', error);
                this.hideTypingIndicator();
                this.handleError(error, message);
            }

            // Auto-scroll to bottom
            this.scrollToBottom();
        }

        sendQuickMessage(message) {
            $('#wc-ai-chat-input').val(message);
            this.sendMessage();
        }

        async sendToAPI(message, context = {}) {
            const data = {
                message: message,
                customer_id: this.getCustomerId(),
                context: context
            };

            const response = await fetch(wcAiChat.restUrl + 'message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wcAiChat.nonce
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        }

        addMessage(content, sender, data = null) {
            const timestamp = this.getCurrentTime();
            const messageId = 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            const messageHtml = this.buildMessageHtml(content, sender, timestamp, messageId, data);
            
            $('#wc-ai-chat-messages').append(messageHtml);
            
            // Store in history
            this.messageHistory.push({
                id: messageId,
                content: content,
                sender: sender,
                timestamp: timestamp,
                data: data
            });

            this.saveConversationHistory();
            this.scrollToBottom();
        }

        buildMessageHtml(content, sender, timestamp, messageId, data = null) {
            const messageClass = sender === 'user' ? 'user-message' : 'bot-message';
            let actionsHtml = '';
            let additionalContent = '';

            // Handle special data types
            if (data && sender === 'bot') {
                if (data.action === 'redirect_to_checkout') {
                    actionsHtml = `
                        <div class="message-actions">
                            <a href="${data.checkout_url}" class="action-button" target="_blank">
                                🛒 Complete Order (${data.total_amount})
                            </a>
                        </div>
                    `;
                } else if (data.action === 'items_added_to_cart') {
                    actionsHtml = `
                        <div class="message-actions">
                            <a href="${data.cart_url}" class="action-button">
                                🛒 View Cart (${data.total_items} items)
                            </a>
                            <button class="action-button secondary" data-action="continue_shopping">
                                Continue Shopping
                            </button>
                        </div>
                    `;
                } else if (data.suggestions && data.suggestions.length > 0) {
                    additionalContent = this.buildProductRecommendations(data.suggestions);
                } else if (data.action === 'request_product_details' && data.suggestions) {
                    additionalContent = this.buildProductRecommendations(data.suggestions);
                    actionsHtml = `
                        <div class="message-actions">
                            <button class="action-button" data-action="browse_products">
                                Browse All Products
                            </button>
                        </div>
                    `;
                }
            }

            return `
                <div class="message ${messageClass}" data-message-id="${messageId}">
                    <div class="message-content">
                        <p>${this.formatMessageContent(content)}</p>
                        ${additionalContent}
                    </div>
                    ${actionsHtml}
                    <div class="message-time">${timestamp}</div>
                </div>
            `;
        }

        buildProductRecommendations(products) {
            if (!products || products.length === 0) return '';

            const productsHtml = products.map(product => `
                <div class="product-item" data-product-id="${product.product_id || product.id}">
                    <div class="product-image"></div>
                    <div class="product-details">
                        <div class="product-name">${product.name}</div>
                        <div class="product-price">${product.price}</div>
                    </div>
                </div>
            `).join('');

            return `
                <div class="product-recommendations">
                    <div style="font-weight: 500; margin-bottom: 8px; font-size: 13px;">Recommended for you:</div>
                    ${productsHtml}
                </div>
            `;
        }

        formatMessageContent(content) {
            // Convert URLs to links
            content = content.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
            
            // Convert line breaks to <br>
            content = content.replace(/\n/g, '<br>');
            
            // Add emoji support for certain keywords
            content = content.replace(/\b(order|shipping|delivery)\b/gi, '📦 $1');
            content = content.replace(/\b(refund|return)\b/gi, '🔄 $1');
            content = content.replace(/\b(help|support)\b/gi, '💬 $1');
            
            return content;
        }

        showTypingIndicator() {
            this.isTyping = true;
            $('#wc-ai-chat-typing').show();
            this.scrollToBottom();
        }

        hideTypingIndicator() {
            this.isTyping = false;
            $('#wc-ai-chat-typing').hide();
        }

        handleError(error, originalMessage) {
            if (this.retryCount < this.maxRetries) {
                this.retryCount++;
                
                const retryHtml = `
                    <div class="message bot-message error">
                        <div class="message-content">
                            <p>I encountered an issue. Let me try again...</p>
                            <div class="message-actions">
                                <button class="action-button" data-action="retry" data-message="${originalMessage}">
                                    🔄 Retry
                                </button>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                `;
                
                $('#wc-ai-chat-messages').append(retryHtml);
            } else {
                const errorHtml = `
                    <div class="message bot-message error">
                        <div class="message-content">
                            <p>I'm experiencing technical difficulties. Please try again later or contact our support team directly.</p>
                            <div class="message-actions">
                                <button class="action-button" data-action="contact_support">
                                    📞 Contact Support
                                </button>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                `;
                
                $('#wc-ai-chat-messages').append(errorHtml);
            }
            
            this.scrollToBottom();
        }

        handleBotAction(data) {
            switch (data.action) {
                case 'redirect_to_checkout':
                    this.trackEvent('checkout_redirect', { order_id: data.order_id });
                    break;
                    
                case 'items_added_to_cart':
                    this.trackEvent('items_added_to_cart', { total_items: data.total_items });
                    break;
                    
                case 'product_unavailable':
                    this.showNotification('Product is currently unavailable', 'warning');
                    break;
                    
                case 'insufficient_stock':
                    this.showNotification(`Only ${data.available_quantity} items available`, 'warning');
                    break;
            }
        }

        handleActionButton(action, data) {
            switch (action) {
                case 'retry':
                    if (data.message) {
                        $('#wc-ai-chat-input').val(data.message);
                        this.sendMessage();
                    }
                    break;
                    
                case 'contact_support':
                    window.open('/contact/', '_blank');
                    break;
                    
                case 'browse_products':
                    window.open('/shop/', '_blank');
                    break;
                    
                case 'continue_shopping':
                    this.sendQuickMessage('Show me more products');
                    break;
            }
        }

        handleProductClick(productId) {
            const context = { products: [{ id: productId, quantity: 1 }] };
            this.sendQuickMessage(`Tell me about product ${productId}`, context);
        }

        autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
        }

        toggleSendButton() {
            const hasText = $('#wc-ai-chat-input').val().trim().length > 0;
            $('#wc-ai-chat-send').prop('disabled', !hasText || this.isTyping);
        }

        scrollToBottom() {
            const messagesContainer = $('#wc-ai-chat-messages');
            messagesContainer.animate({
                scrollTop: messagesContainer[0].scrollHeight
            }, 300);
        }

        getCurrentTime() {
            const now = new Date();
            return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        getCustomerId() {
            return wcAiChat.currentUserId || this.getOrCreateGuestId();
        }

        getOrCreateGuestId() {
            let guestId = localStorage.getItem('wc_ai_chat_guest_id');
            if (!guestId) {
                guestId = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('wc_ai_chat_guest_id', guestId);
            }
            return guestId;
        }

        saveConversationHistory() {
            const data = {
                history: this.messageHistory.slice(-20), // Keep last 20 messages
                timestamp: Date.now()
            };
            localStorage.setItem('wc_ai_chat_history', JSON.stringify(data));
        }

        loadConversationHistory() {
            try {
                const saved = localStorage.getItem('wc_ai_chat_history');
                if (saved) {
                    const data = JSON.parse(saved);
                    // Only load if less than 24 hours old
                    if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                        this.messageHistory = data.history || [];
                        this.restoreMessages();
                    }
                }
            } catch (e) {
                console.warn('Failed to load conversation history:', e);
            }
        }

        restoreMessages() {
            const welcomeMessage = $('.welcome-message');
            
            this.messageHistory.forEach(msg => {
                if (msg.sender !== 'bot' || msg.content !== wcAiChat.strings.welcomeMessage) {
                    const messageHtml = this.buildMessageHtml(
                        msg.content,
                        msg.sender,
                        msg.timestamp,
                        msg.id,
                        msg.data
                    );
                    welcomeMessage.after(messageHtml);
                }
            });

            // Hide quick actions if conversation exists
            if (this.messageHistory.length > 0) {
                $('#wc-ai-chat-quick-actions').hide();
            }
        }

        hasInteracted() {
            return localStorage.getItem('wc_ai_chat_interacted') === 'true';
        }

        markAsInteracted() {
            localStorage.setItem('wc_ai_chat_interacted', 'true');
        }

        updateNotificationBadge() {
            // Check for unread messages or special conditions
            const hasUnread = this.checkForUnreadMessages();
            if (hasUnread) {
                $('.notification-badge').text('1').show();
            }
        }

        checkForUnreadMessages() {
            // Implement logic to check for unread messages
            // This could be based on server-side notifications
            return false;
        }

        showNotification(message, type = 'info') {
            // Create temporary notification
            const notification = $(`
                <div class="chat-notification ${type}" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'warning' ? '#fed7d7' : '#c6f6d5'};
                    color: ${type === 'warning' ? '#742a2a' : '#22543d'};
                    padding: 10px 15px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    z-index: 10000;
                    font-size: 14px;
                ">
                    ${message}
                </div>
            `);

            $('body').append(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 3000);
        }

        trackEvent(eventType, data = {}) {
            // Send analytics event
            if (typeof gtag !== 'undefined') {
                gtag('event', eventType, {
                    event_category: 'AI Chat',
                    ...data
                });
            }

            // Custom tracking
            const eventData = {
                event: eventType,
                timestamp: Date.now(),
                customer_id: this.getCustomerId(),
                ...data
            };

            // Send to your analytics endpoint
            fetch(wcAiChat.restUrl + 'analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wcAiChat.nonce
                },
                body: JSON.stringify(eventData)
            }).catch(e => console.warn('Analytics tracking failed:', e));
        }

        handlePageHidden() {
            // Pause any ongoing animations or processes
            this.isPageVisible = false;
        }

        handlePageVisible() {
            // Resume processes
            this.isPageVisible = true;
            
            // Check for new messages if chat was open
            if (this.isOpen) {
                this.checkForNewMessages();
            }
        }

        async checkForNewMessages() {
            // Implement server-side message checking if needed
            // This would be useful for multi-agent support
        }

        bindChatOpenEvent() {
            // Custom event for when chat opens
            $(document).on('wc_ai_chat_opened', () => {
                this.onChatOpened();
            });
        }

        onChatOpened() {
            // Initialize any additional features when chat opens
            this.initializeFeatures();
        }

        initializeFeatures() {
            // Voice input feature (if supported)
            this.initVoiceInput();
            
            // File upload feature
            this.initFileUpload();
            
            // Emoji picker
            this.initEmojiPicker();
        }

        initVoiceInput() {
            // Implementation for voice input would go here
            // Using Web Speech API if available
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                // Add voice input button and functionality
            }
        }

        initFileUpload() {
            // Implementation for file upload would go here
            // For sharing order screenshots, product images, etc.
        }

        initEmojiPicker() {
            // Simple emoji support
            const commonEmojis = ['👍', '👎', '😊', '😕', '❤️', '🔥', '💯', '🙏'];
            // Could add emoji picker UI
        }

        // Public API methods
        open() {
            this.openChat();
        }

        close() {
            this.closeChat();
        }

        sendCustomMessage(message, context = {}) {
            if (typeof message === 'string') {
                $('#wc-ai-chat-input').val(message);
                return this.sendToAPI(message, context);
            }
        }

        addCustomMessage(content, sender = 'bot') {
            this.addMessage(content, sender);
        }

        // Cleanup method
        destroy() {
            $(document).off('.wc-ai-chat');
            $('#wc-ai-chat-widget').remove();
            this.messageHistory = [];
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize if not in admin area
        if (typeof wcAiChat !== 'undefined') {
            window.wcAiChatWidget = new WCAIChatWidget();
            
            // Expose public API
            window.WCAIChat = {
                open: () => window.wcAiChatWidget.open(),
                close: () => window.wcAiChatWidget.close(),
                sendMessage: (msg, ctx) => window.wcAiChatWidget.sendCustomMessage(msg, ctx),
                addMessage: (content, sender) => window.wcAiChatWidget.addCustomMessage(content, sender)
            };

            // Trigger custom event
            $(document).trigger('wc_ai_chat_initialized');
        }
    });

    // Handle page unload
    $(window).on('beforeunload', function() {
        if (window.wcAiChatWidget) {
            window.wcAiChatWidget.saveConversationHistory();
        }
    });

})(jQuery);