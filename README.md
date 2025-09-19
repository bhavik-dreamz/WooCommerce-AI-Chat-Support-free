# WooCommerce AI Chat Support

🤖 **Intelligent AI-powered customer support chat widget for WooCommerce stores with Groq and OpenAI integration.**

Transform your customer support with advanced AI that can handle orders, product inquiries, recommendations, and much more - all through a beautiful, modern chat interface.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0+-orange.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-red.svg)

## 🌟 Features

### 🚀 **Dual AI Engine**
- **Groq API**: Ultra-fast responses (sub-second) with 6,000 requests/minute on free tier
- **OpenAI GPT-3.5**: High-quality, reliable AI responses
- **Smart Fallback**: Automatic switching between providers for maximum uptime
- **Context-Aware**: Remembers customer history and preferences

### 💬 **Beautiful Chat Widget**
- Modern, responsive design with smooth animations
- Customizable themes and positioning
- Mobile-optimized with full-screen support
- Real-time typing indicators and notifications
- Message history persistence
- Keyboard shortcuts (Enter to send, Escape to close)

### 🛒 **E-commerce Integration**
- **Direct Ordering**: "I want to buy 2 red shirts" → Instant order creation
- **Cart Management**: Add/remove items through natural language
- **Order Tracking**: Real-time order status updates
- **Quick Reorder**: "Order the same as last time"
- **Smart Recommendations**: AI-powered product suggestions
- **Stock Validation**: Real-time inventory checking

### 🧠 **Advanced AI Capabilities**
- **10+ Query Types**: Order status, product info, refunds, technical support, etc.
- **Sentiment Analysis**: Detects customer emotions (positive/negative/neutral)
- **Natural Language Processing**: Understands complex customer requests
- **Multi-language Support**: Ready for internationalization

### 📊 **Analytics & Insights**
- **Conversation Metrics**: Total chats, response times, resolution rates
- **Customer Analytics**: Purchase patterns, favorite categories
- **Inventory Management**: AI-powered stock prediction and alerts
- **Sales Analytics**: Revenue trends, top products, customer segmentation

### 📧 **Marketing Automation**
- **Birthday Campaigns**: Automatic personalized discount emails
- **Win-back Campaigns**: Re-engage inactive customers (15% off)
- **Replenishment Reminders**: Smart reorder suggestions
- **Customer Segmentation**: High-value, frequent, and regular customer targeting

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 3.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **API Keys**: Groq API (recommended) or OpenAI API

## 🚀 Quick Start

### 1. **Download & Install**
```bash
# Download plugin files
git clone https://github.com/your-repo/wc-ai-chat-support.git

# Upload to WordPress
wp-content/plugins/woocommerce-ai-chat-support/
```

### 2. **Activate Plugin**
1. Go to **WordPress Admin → Plugins**
2. Find "WooCommerce AI Chat Support"
3. Click **Activate**

### 3. **Get API Keys**

#### **Groq API (Recommended - Free Tier Available)**
1. Sign up at [console.groq.com](https://console.groq.com/)
2. Create new API key
3. Copy key: `gsk_xxxxxxxxxxxxxxxxxx`

#### **OpenAI API (Paid)**
1. Sign up at [platform.openai.com](https://platform.openai.com/)
2. Create new API key
3. Copy key: `sk-xxxxxxxxxxxxxxxxxx`

### 4. **Configure Settings**
1. Go to **AI Chat → Settings** in WordPress admin
2. Enter your API keys
3. Choose AI provider:
   - **Both** (Recommended): Groq primary, OpenAI fallback
   - **Groq Only**: Fastest and cheapest
   - **OpenAI Only**: Most reliable
4. Customize appearance and messages
5. Save settings

### 5. **Test Chat Widget**
- Chat widget appears automatically on your website
- Test with sample queries:
  - "Where is my order?"
  - "I want to buy a blue t-shirt"
  - "Show me product recommendations"

## ⚙️ Configuration

### **Basic Settings**
```php
// WordPress Admin → AI Chat → Settings

✅ Enable Chat Widget
🔑 Groq API Key: gsk_xxxxxxxxxxxxx
🔑 OpenAI API Key: sk_xxxxxxxxxxxxx  
🤖 AI Provider: Both (Groq + OpenAI)
📍 Widget Position: Bottom Right
💬 Welcome Message: "Hi! How can I help you today?"
🎨 Theme: Modern
```

### **Advanced Options**
```php
// Custom configuration in wp-config.php
define('WC_AI_CHAT_MAX_MESSAGES', 50);
define('WC_AI_CHAT_CACHE_TIME', 3600);
define('WC_AI_CHAT_DEBUG', true);
```

### **WooCommerce API Setup** (Optional)
For external API access:
1. Go to **WooCommerce → Settings → Advanced → REST API**
2. Create new key with **Read/Write** permissions
3. Add keys to plugin settings

## 🎨 Customization

### **CSS Customization**
```css
/* Custom chat widget styles */
.wc-ai-chat-widget {
    --primary-color: #your-brand-color;
    --border-radius: 15px;
}

/* Custom position */
.wc-ai-chat-widget.bottom-left {
    left: 20px !important;
}
```

### **JavaScript API**
```javascript
// Open chat programmatically
WCAIChat.open();

// Send custom message
WCAIChat.sendMessage("Hello from my website!", {
    context: { page: 'product', id: 123 }
});

// Add system message
WCAIChat.addMessage("Welcome to our store!", "bot");

// Listen for events
$(document).on('wc_ai_chat_message_sent', function(e, data) {
    console.log('Message sent:', data);
});
```

### **PHP Hooks & Filters**
```php
// Customize AI response
add_filter('wc_ai_chat_response', function($response, $query, $customer_id) {
    // Add custom logic
    return $response;
}, 10, 3);

// Modify product recommendations
add_filter('wc_ai_chat_recommendations', function($products, $customer_id) {
    // Custom recommendation logic
    return $products;
}, 10, 2);

// Custom query classification
add_filter('wc_ai_chat_query_type', function($type, $query) {
    if (strpos($query, 'vip') !== false) {
        return 'vip_support';
    }
    return $type;
}, 10, 2);
```

## 📊 Analytics Dashboard

### **Conversation Metrics**
- Total conversations (last 30 days)
- Unique customers served
- Average response time
- Customer satisfaction scores
- Most common query types

### **Performance Analytics**
- AI provider response times
- Success/failure rates
- Cost tracking (API usage)
- Peak usage times

### **Business Insights**
- Revenue generated through chat
- Products recommended vs purchased
- Customer conversion rates
- Support ticket reduction

## 🛠️ API Reference

### **REST Endpoints**

#### **Send Message**
```http
POST /wp-json/wc-ai-chat/v1/message
Content-Type: application/json

{
    "message": "I want to buy a red shirt",
    "customer_id": 123,
    "context": {
        "page": "product",
        "product_id": 456
    }
}
```

#### **Get Recommendations**
```http
GET /wp-json/wc-ai-chat/v1/recommendations/123
```

#### **Place Order**
```http
POST /wp-json/wc-ai-chat/v1/order

{
    "customer_id": 123,
    "products": [
        {"id": 456, "quantity": 2},
        {"id": 789, "quantity": 1}
    ]
}
```

### **Response Format**
```json
{
    "success": true,
    "response": "I've added 2 red shirts to your cart!",
    "type": "add_to_cart",
    "sentiment": "positive",
    "data": {
        "action": "items_added_to_cart",
        "cart_url": "https://yourstore.com/cart",
        "total_items": 2
    },
    "timestamp": "2024-01-15 10:30:00"
}
```

## 📈 Marketing Automation

### **Campaign Types**

#### **Birthday Campaigns**
- Automatically detects customer birthdays
- Sends personalized discount codes
- Customizable discount percentages
- Email integration ready

#### **Win-back Campaigns**
- Targets customers inactive 60-180 days
- Personalized product recommendations
- 15% discount offers
- A/B testing support

#### **Replenishment Reminders**
- Analyzes purchase patterns
- Identifies consumable products
- Smart timing based on usage patterns
- Cross-sell opportunities

### **Customer Segmentation**
```php
// Automatic segmentation
$segments = [
    'high_value' => '$1000+ lifetime value',
    'frequent' => '5+ orders placed', 
    'regular' => 'Standard customers',
    'at_risk' => '90+ days inactive',
    'new' => 'First-time buyers'
];
```

## 🔧 Troubleshooting

### **Common Issues**

#### **Chat Widget Not Appearing**
```php
// Check plugin activation
if (!class_exists('WC_AI_Chat_Plugin')) {
    // Plugin not activated
}

// Check WooCommerce dependency
if (!class_exists('WooCommerce')) {
    // WooCommerce required
}

// Check settings
$enabled = get_option('wc_ai_chat_enabled', '1');
```

#### **API Errors**
```php
// Check API keys
$groq_key = get_option('wc_ai_chat_groq_key');
$openai_key = get_option('wc_ai_chat_openai_key');

// Enable debug mode
define('WC_AI_CHAT_DEBUG', true);

// Check error logs
wp-content/debug.log
```

#### **Performance Issues**
```php
// Optimize database
DELETE FROM wp_wc_ai_chat_conversations 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);

// Clear cache
wp cache flush

// Reduce API calls
update_option('wc_ai_chat_cache_time', 7200);
```

### **Debug Mode**
```php
// Enable in wp-config.php
define('WC_AI_CHAT_DEBUG', true);

// View debug info
wp-admin/admin.php?page=wc-ai-chat-debug
```

## 🔒 Security

### **Data Protection**
- All API keys encrypted in database
- Customer data GDPR compliant
- Secure nonce validation
- SQL injection protection
- XSS prevention

### **Privacy Features**
- Option to disable conversation logging
- Automatic data cleanup (90 days)
- Customer data export tools
- Right to be forgotten compliance

## 🌐 Internationalization

### **Supported Languages**
- English (default)
- Spanish
- French
- German
- Italian
- Portuguese

### **Adding Translations**
```php
// Create language file
languages/wc-ai-chat-es_ES.po

// Translate strings
__('Hello', 'wc-ai-chat') → "Hola"

// Compile
msgfmt wc-ai-chat-es_ES.po -o wc-ai-chat-es_ES.mo
```

## 📊 Performance

### **Benchmarks**
- **Groq API**: ~200ms average response time
- **OpenAI API**: ~800ms average response time
- **Database Queries**: <50ms per chat message
- **Memory Usage**: <5MB additional

### **Optimization Tips**
1. Use Groq for faster responses
2. Enable caching for repeated queries
3. Implement rate limiting for heavy usage
4. Regular database cleanup
5. CDN for static assets

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md).

### **Development Setup**
```bash
# Clone repository
git clone https://github.com/your-repo/wc-ai-chat-support.git

# Install dependencies
composer install
npm install

# Setup local WordPress
wp-env start

# Run tests
npm test
```

### **Coding Standards**
- WordPress Coding Standards
- PHPStan Level 5
- ESLint for JavaScript
- Automated testing with PHPUnit

## 📝 Changelog

### **v1.0.0** - 2024-01-15
- ✨ Initial release
- 🤖 Groq and OpenAI integration
- 💬 Modern chat widget
- 🛒 E-commerce features
- 📊 Analytics dashboard
- 📧 Marketing automation

## 🆘 Support

### **Documentation**
- [Plugin Documentation](https://docs.yoursite.com/wc-ai-chat)
- [Video Tutorials](https://youtube.com/playlist/wc-ai-chat)
- [FAQ](https://support.yoursite.com/faq)

### **Get Help**
- 🎫 [Support Tickets](https://support.yoursite.com)
- 💬 [Community Forum](https://community.yoursite.com)
- 📧 [Email Support](mailto:support@yoursite.com)
- 📱 [Discord Community](https://discord.gg/wc-ai-chat)

### **Professional Services**
- 🎨 Custom theme integration
- 🔧 Advanced customization
- 📊 Custom analytics
- 🚀 Performance optimization
- 📧 Email marketing setup

## 📄 License

This plugin is licensed under the [GPL v2 or later](LICENSE).

```
WooCommerce AI Chat Support
Copyright (C) 2024 Your Company

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## 🙏 Credits

### **Built With**
- [WordPress](https://wordpress.org/) - CMS Platform
- [WooCommerce](https://woocommerce.com/) - E-commerce Plugin
- [Groq](https://groq.com/) - Fast AI Inference
- [OpenAI](https://openai.com/) - ChatGPT API
- [jQuery](https://jquery.com/) - JavaScript Library

### **Special Thanks**
- WordPress community for amazing documentation
- WooCommerce team for extensible APIs
- Groq team for incredibly fast AI inference
- OpenAI for revolutionary language models

---

## 🚀 Ready to Transform Your Customer Support?

**[Download Now](https://github.com/your-repo/wc-ai-chat-support/releases/latest)** | **[View Demo](https://demo.yoursite.com)** | **[Get Support](https://support.yoursite.com)**

⭐ **If you find this plugin helpful, please consider giving it a star on GitHub!**

---

*Made with ❤️ for the WordPress & WooCommerce community*