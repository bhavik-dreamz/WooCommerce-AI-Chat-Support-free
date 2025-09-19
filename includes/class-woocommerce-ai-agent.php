<?php
/**
 * WooCommerce AI Agent Class
 * Handles AI-powered customer support, recommendations, and automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceAIAgent {
    private $woocommerce;
    private $openai_api_key;
    private $groq_api_key;
    private $ai_provider;
    private $db;
    
    public function __construct($wc_api_key, $wc_api_secret, $store_url, $ai_config = array()) {
        // AI Configuration
        $this->openai_api_key = $ai_config['openai_key'] ?? null;
        $this->groq_api_key = $ai_config['groq_key'] ?? null;
        $this->ai_provider = $ai_config['provider'] ?? 'openai';
        
        // Initialize WooCommerce API if keys provided
        if ($wc_api_key && $wc_api_secret) {
            $this->woocommerce = new WC_API_Client(
                $store_url,
                $wc_api_key,
                $wc_api_secret,
                array('wp_api' => true, 'version' => 'wc/v3')
            );
        } else {
            // Use WordPress/WooCommerce functions directly
            $this->woocommerce = null;
        }
        
        $this->initDatabase();
    }
    
    private function initDatabase() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Main customer query handler
     */
    public function handleCustomerQuery($customer_id, $query, $context = array()) {
        try {
            $sentiment = $this->analyzeSentiment($query);
            $query_type = $this->classifyQuery($query);
            
            $response = "";
            $additional_data = array();
            
            switch($query_type) {
                case 'order_status':
                    $response = $this->handleOrderStatusQuery($customer_id, $query);
                    break;
                case 'product_inquiry':
                    $response = $this->handleProductInquiry($query);
                    break;
                case 'refund_request':
                    $response = $this->handleRefundRequest($customer_id, $query);
                    break;
                case 'technical_support':
                    $response = $this->handleTechnicalSupport($query);
                    break;
                case 'place_order':
                    $order_result = $this->handleOrderPlacement($customer_id, $query, $context);
                    $response = $order_result['response'];
                    $additional_data = $order_result['data'];
                    break;
                case 'add_to_cart':
                    $cart_result = $this->handleAddToCart($customer_id, $query, $context);
                    $response = $cart_result['response'];
                    $additional_data = $cart_result['data'];
                    break;
                case 'quick_reorder':
                    $reorder_result = $this->handleQuickReorder($customer_id, $query);
                    $response = $reorder_result['response'];
                    $additional_data = $reorder_result['data'];
                    break;
                default:
                    $customer_context = $this->getCustomerContext($customer_id);
                    $response = $this->generateAIResponse($query, $customer_context);
            }
            
            $this->logInteraction($customer_id, $query_type, $query, $response, $sentiment);
            
            return array(
                'response' => $response,
                'sentiment' => $sentiment,
                'type' => $query_type,
                'confidence' => 0.85,
                'data' => $additional_data
            );
            
        } catch(Exception $e) {
            error_log("Customer support error: " . $e->getMessage());
            return array(
                'response' => "I apologize, but I'm experiencing technical difficulties. Please contact our support team directly.",
                'sentiment' => 'neutral',
                'type' => 'error',
                'confidence' => 0.0,
                'data' => array()
            );
        }
    }
    
    /**
     * Query classification - determines what type of query the customer is asking
     */
    private function classifyQuery($query) {
        $query_lower = strtolower($query);
        
        // Order status queries
        if(preg_match('/\b(order|shipping|delivery|status|track|where.*order|order.*status|shipped|tracking)\b/', $query_lower)) {
            return 'order_status';
        }
        
        // Product inquiry queries
        if(preg_match('/\b(product|item|specification|detail|about|describe|tell.*about|what.*is|information|features)\b/', $query_lower)) {
            return 'product_inquiry';
        }
        
        // Refund/return queries
        if(preg_match('/\b(refund|return|money.*back|cancel.*order|exchange|replacement|not.*satisfied)\b/', $query_lower)) {
            return 'refund_request';
        }
        
        // Technical support queries
        if(preg_match('/\b(technical|bug|error|not.*working|problem|issue|broken|fix|help.*with|troubleshoot)\b/', $query_lower)) {
            return 'technical_support';
        }
        
        // Order placement queries
        if(preg_match('/\b(buy|purchase|order|get|want.*to.*buy|i.*need|looking.*to.*buy|checkout)\b/', $query_lower)) {
            return 'place_order';
        }
        
        // Add to cart queries
        if(preg_match('/\b(add.*to.*cart|add.*cart|cart|put.*in.*cart|add.*this)\b/', $query_lower)) {
            return 'add_to_cart';
        }
        
        // Quick reorder queries
        if(preg_match('/\b(reorder|order.*again|same.*as.*last.*time|repeat.*order|buy.*again|previous.*order)\b/', $query_lower)) {
            return 'quick_reorder';
        }
        
        // Recommendation queries
        if(preg_match('/\b(recommend|suggestion|what.*should|best.*for|popular|trending|similar)\b/', $query_lower)) {
            return 'recommendation_request';
        }
        
        // Price/discount queries
        if(preg_match('/\b(price|cost|discount|sale|offer|deal|coupon|promo|cheap|expensive)\b/', $query_lower)) {
            return 'pricing_inquiry';
        }
        
        // Shipping queries
        if(preg_match('/\b(shipping|delivery|when.*arrive|how.*long|fast.*shipping|free.*shipping)\b/', $query_lower)) {
            return 'shipping_inquiry';
        }
        
        return 'general';
    }
    
    /**
     * Sentiment analysis - determines customer mood
     */
    private function analyzeSentiment($text) {
        $positive_words = array('good', 'great', 'excellent', 'happy', 'satisfied', 'love', 'amazing', 'perfect', 'awesome', 'wonderful', 'fantastic', 'pleased', 'glad', 'thank', 'thanks');
        $negative_words = array('bad', 'terrible', 'awful', 'hate', 'disappointed', 'angry', 'frustrated', 'horrible', 'worst', 'sucks', 'annoyed', 'upset', 'mad', 'furious', 'disgusted');
        
        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach($positive_words as $word) {
            if(strpos($text_lower, $word) !== false) $positive_count++;
        }
        
        foreach($negative_words as $word) {
            if(strpos($text_lower, $word) !== false) $negative_count++;
        }
        
        if($positive_count > $negative_count) return 'positive';
        if($negative_count > $positive_count) return 'negative';
        return 'neutral';
    }
    
    /**
     * Generate AI-powered responses using OpenAI or Groq
     */
    private function generateAIResponse($query, $context = array()) {
        try {
            $system_prompt = $this->buildSystemPrompt($context);
            
            switch($this->ai_provider) {
                case 'openai':
                    return $this->getOpenAIResponse($query, $system_prompt);
                case 'groq':
                    return $this->getGroqResponse($query, $system_prompt);
                case 'both':
                    $groq_response = $this->getGroqResponse($query, $system_prompt);
                    if($groq_response !== null) {
                        return $groq_response;
                    }
                    return $this->getOpenAIResponse($query, $system_prompt);
                default:
                    return $this->getFallbackResponse($query);
            }
        } catch(Exception $e) {
            error_log("AI Response error: " . $e->getMessage());
            return $this->getFallbackResponse($query);
        }
    }
    
    private function buildSystemPrompt($context = array()) {
        $store_info = $context['store_info'] ?? array();
        $customer_info = $context['customer_info'] ?? array();
        
        $prompt = "You are a helpful AI customer service assistant for a WooCommerce e-commerce store. ";
        $prompt .= "You should be friendly, professional, and helpful. ";
        
        if(!empty($store_info)) {
            $prompt .= "Store information: " . json_encode($store_info) . " ";
        }
        
        if(!empty($customer_info)) {
            $prompt .= "Customer context: " . json_encode($customer_info) . " ";
        }
        
        $prompt .= "Guidelines:\n";
        $prompt .= "- Be concise but informative\n";
        $prompt .= "- Always maintain a helpful and professional tone\n";
        $prompt .= "- If you can't help with something, politely direct to human support\n";
        $prompt .= "- For product questions, be specific about features and benefits\n";
        $prompt .= "- For order issues, show empathy and provide clear next steps\n";
        $prompt .= "- Always end with asking if there's anything else you can help with\n";
        
        return $prompt;
    }
    
    private function getOpenAIResponse($query, $system_prompt) {
        if(!$this->openai_api_key) {
            return null;
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $query)
            ),
            'max_tokens' => 500,
            'temperature' => 0.7
        );
        
        $response = $this->makeAPICall($url, $data, array(
            'Authorization: Bearer ' . $this->openai_api_key,
            'Content-Type: application/json'
        ));
        
        if($response && isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }
        
        return null;
    }
    
    private function getGroqResponse($query, $system_prompt) {
        if(!$this->groq_api_key) {
            return null;
        }
        
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        
        $data = array(
            'model' => 'mixtral-8x7b-32768',
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $query)
            ),
            'max_tokens' => 500,
            'temperature' => 0.7
        );
        
        $response = $this->makeAPICall($url, $data, array(
            'Authorization: Bearer ' . $this->groq_api_key,
            'Content-Type: application/json'
        ));
        
        if($response && isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }
        
        return null;
    }
    
    private function makeAPICall($url, $data, $headers) {
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => str_replace('Authorization: ', '', $headers[0]),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log("API Error: " . $response->get_error_message());
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200) {
            error_log("API Error - HTTP Code: {$http_code}, Response: {$body}");
            return null;
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Product recommendation engine
     */
    public function getProductRecommendations($customer_id, $limit = 5) {
        try {
            if (!function_exists('wc_get_orders')) {
                return array();
            }
            
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'limit' => 10,
                'status' => 'completed'
            ));
            
            $purchased_categories = array();
            $purchased_products = array();
            
            foreach($orders as $order) {
                foreach($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $product = wc_get_product($product_id);
                    
                    if ($product) {
                        $purchased_products[] = $product_id;
                        $categories = wp_get_post_terms($product_id, 'product_cat');
                        
                        foreach($categories as $category) {
                            $purchased_categories[$category->term_id] = 
                                ($purchased_categories[$category->term_id] ?? 0) + 1;
                        }
                    }
                }
            }
            
            if (empty($purchased_categories)) {
                return $this->getPopularProducts($limit);
            }
            
            arsort($purchased_categories);
            $top_categories = array_slice(array_keys($purchased_categories), 0, 3);
            
            $recommendations = array();
            foreach($top_categories as $category_id) {
                $products = get_posts(array(
                    'post_type' => 'product',
                    'posts_per_page' => 10,
                    'post__not_in' => $purchased_products,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $category_id
                        )
                    )
                ));
                
                foreach($products as $product_post) {
                    if(count($recommendations) >= $limit) break;
                    
                    $product = wc_get_product($product_post->ID);
                    if ($product && $product->is_in_stock()) {
                        $confidence = $this->calculateRecommendationConfidence(
                            $customer_id, $product, $purchased_categories
                        );
                        
                        $recommendations[] = array(
                            'product_id' => $product->get_id(),
                            'name' => $product->get_name(),
                            'price' => $product->get_price(),
                            'confidence' => $confidence,
                            'reason' => "Based on your purchase history"
                        );
                    }
                }
            }
            
            return $recommendations;
            
        } catch(Exception $e) {
            error_log("Recommendation error: " . $e->getMessage());
            return $this->getPopularProducts($limit);
        }
    }
    
    private function getPopularProducts($limit = 5) {
        $products = wc_get_products(array(
            'limit' => $limit,
            'orderby' => 'popularity',
            'status' => 'publish'
        ));
        
        $recommendations = array();
        foreach($products as $product) {
            $recommendations[] = array(
                'product_id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'confidence' => 0.5,
                'reason' => "Popular product"
            );
        }
        
        return $recommendations;
    }
    
    private function calculateRecommendationConfidence($customer_id, $product, $categories) {
        $base_confidence = 0.5;
        
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat');
        foreach($product_categories as $cat) {
            if(isset($categories[$cat->term_id])) {
                $base_confidence += ($categories[$cat->term_id] * 0.1);
            }
        }
        
        if($product->get_average_rating() > 4) {
            $base_confidence += 0.15;
        }
        
        if($product->get_review_count() > 10) {
            $base_confidence += 0.1;
        }
        
        return min($base_confidence, 1.0);
    }
    
    /**
     * Specific query handlers
     */
    private function handleOrderStatusQuery($customer_id, $query) {
        try {
            if (!function_exists('wc_get_orders')) {
                return "I'm having trouble accessing order information. Please contact customer service with your order number.";
            }
            
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'limit' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if(empty($orders)) {
                return "I don't see any recent orders for your account. If you placed an order recently, it may still be processing.";
            }
            
            $latest_order = $orders[0];
            $order_number = $latest_order->get_order_number();
            $status = $latest_order->get_status();
            $date = $latest_order->get_date_created()->format('F j, Y');
            
            return "Your most recent order #{$order_number} from {$date} is currently {$status}. " . 
                   $this->getStatusExplanation($status);
            
        } catch(Exception $e) {
            return "I'm having trouble accessing your order information. Please contact customer service with your order number.";
        }
    }
    
    private function getStatusExplanation($status) {
        $explanations = array(
            'pending' => 'We\'re processing your payment and preparing your order.',
            'processing' => 'Your order is being prepared for shipment.',
            'on-hold' => 'Your order is on hold pending payment or stock availability.',
            'completed' => 'Your order has been completed and shipped.',
            'cancelled' => 'This order has been cancelled.',
            'refunded' => 'This order has been refunded.',
            'failed' => 'Payment for this order failed. Please try again.'
        );
        
        return $explanations[$status] ?? 'Please contact customer service for more details.';
    }
    
    private function handleProductInquiry($query) {
        try {
            $products = $this->searchProductsByQuery($query);
            
            if(empty($products)) {
                $context = array(
                    'type' => 'product_not_found',
                    'query' => $query
                );
                return $this->generateAIResponse(
                    "A customer is asking about products but I couldn't find specific matches for: {$query}. Please help them find what they're looking for.",
                    $context
                );
            }
            
            $product = $products[0];
            $product_context = array(
                'product' => array(
                    'name' => $product->get_name(),
                    'description' => strip_tags($product->get_description()),
                    'price' => $product->get_price(),
                    'in_stock' => $product->is_in_stock(),
                    'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'))
                )
            );
            
            return $this->generateAIResponse(
                "A customer is asking about this product: {$query}. Here are the product details to help answer their question.",
                $product_context
            );
            
        } catch(Exception $e) {
            error_log("Product inquiry error: " . $e->getMessage());
            return "I'm having trouble finding information about that product. Could you please be more specific about what you're looking for?";
        }
    }
    
    private function handleRefundRequest($customer_id, $query) {
        try {
            if (!function_exists('wc_get_orders')) {
                return "I understand you'd like to request a refund. Please contact our customer service team directly for assistance with returns and refunds.";
            }
            
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'limit' => 3,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            $order_context = array();
            foreach($orders as $order) {
                $order_context[] = array(
                    'number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'total' => $order->get_total(),
                    'date' => $order->get_date_created()->format('Y-m-d')
                );
            }
            
            $context = array(
                'type' => 'refund_request',
                'recent_orders' => $order_context,
                'customer_query' => $query
            );
            
            return $this->generateAIResponse(
                "A customer is requesting a refund or return. Their query: {$query}. Please provide helpful information about the refund process and next steps.",
                $context
            );
            
        } catch(Exception $e) {
            error_log("Refund request error: " . $e->getMessage());
            return "I understand you'd like to request a refund. I'll help you with that process. Could you please provide your order number?";
        }
    }
    
    private function handleTechnicalSupport($query) {
        $context = array(
            'type' => 'technical_support',
            'issue' => $query
        );
        
        return $this->generateAIResponse(
            "A customer is experiencing a technical issue: {$query}. Please provide helpful troubleshooting steps and solutions.",
            $context
        );
    }
    
    /**
     * Order placement handlers
     */
    private function handleOrderPlacement($customer_id, $query, $context = array()) {
        try {
            $products = $this->extractProductsFromQuery($query, $context);
            
            if(empty($products)) {
                return array(
                    'response' => "I'd be happy to help you place an order! Could you please specify which products you'd like to purchase?",
                    'data' => array(
                        'action' => 'request_product_details',
                        'suggestions' => $this->getProductRecommendations($customer_id, 3)
                    )
                );
            }
            
            // For demo purposes, we'll simulate order creation
            // In production, you'd create actual WooCommerce orders
            $total_amount = 0;
            $validated_products = array();
            
            foreach($products as $product_request) {
                if (function_exists('wc_get_product')) {
                    $product = wc_get_product($product_request['id']);
                    
                    if(!$product || $product->get_status() !== 'publish') {
                        return array(
                            'response' => "I'm sorry, but the product '{$product_request['name']}' is not available right now.",
                            'data' => array('action' => 'product_unavailable')
                        );
                    }
                    
                    if($product->managing_stock() && $product->get_stock_quantity() < $product_request['quantity']) {
                        return array(
                            'response' => "I'm sorry, but we only have {$product->get_stock_quantity()} units of '{$product->get_name()}' in stock.",
                            'data' => array(
                                'action' => 'insufficient_stock',
                                'available_quantity' => $product->get_stock_quantity(),
                                'requested_quantity' => $product_request['quantity']
                            )
                        );
                    }
                    
                    $validated_products[] = array(
                        'product_id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'quantity' => $product_request['quantity'],
                        'price' => floatval($product->get_price())
                    );
                    
                    $total_amount += floatval($product->get_price()) * $product_request['quantity'];
                }
            }
            
            // Simulate order creation
            $order_id = rand(1000, 9999);
            $checkout_url = wc_get_checkout_url() . "?order_id=" . $order_id;
            
            return array(
                'response' => "Perfect! I've prepared your order with a total of $" . number_format($total_amount, 2) . ". Click below to complete your purchase.",
                'data' => array(
                    'action' => 'redirect_to_checkout',
                    'order_id' => $order_id,
                    'checkout_url' => $checkout_url,
                    'total_amount' => $total_amount,
                    'items' => $validated_products
                )
            );
            
        } catch(Exception $e) {
            error_log("Order placement error: " . $e->getMessage());
            return array(
                'response' => "I'm having trouble processing your order right now. Please try again later.",
                'data' => array('action' => 'error')
            );
        }
    }
    
    private function handleAddToCart($customer_id, $query, $context = array()) {
        try {
            $products = $this->extractProductsFromQuery($query, $context);
            
            if(empty($products)) {
                return array(
                    'response' => "Which product would you like to add to your cart?",
                    'data' => array(
                        'action' => 'request_product_details',
                        'suggestions' => $this->getProductRecommendations($customer_id, 3)
                    )
                );
            }
            
            $cart_items = array();
            $total_items = 0;
            
            foreach($products as $product_request) {
                if (function_exists('wc_get_product')) {
                    $product = wc_get_product($product_request['id']);
                    
                    if($product && $product->get_status() === 'publish') {
                        $cart_items[] = array(
                            'product_id' => $product->get_id(),
                            'name' => $product->get_name(),
                            'quantity' => $product_request['quantity'],
                            'price' => $product->get_price()
                        );
                        $total_items += $product_request['quantity'];
                    }
                }
            }
            
            if(empty($cart_items)) {
                return array(
                    'response' => "I couldn't find the products you mentioned. Could you please specify the exact product name?",
                    'data' => array('action' => 'product_not_found')
                );
            }
            
            $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart');
            
            $response = "Great! I've added ";
            if(count($cart_items) === 1) {
                $item = $cart_items[0];
                $response .= "{$item['quantity']} x {$item['name']} to your cart.";
            } else {
                $response .= "{$total_items} items to your cart.";
            }
            $response .= " You can review your cart and proceed to checkout when ready.";
            
            return array(
                'response' => $response,
                'data' => array(
                    'action' => 'items_added_to_cart',
                    'cart_url' => $cart_url,
                    'items' => $cart_items,
                    'total_items' => $total_items
                )
            );
            
        } catch(Exception $e) {
            error_log("Add to cart error: " . $e->getMessage());
            return array(
                'response' => "I had trouble adding items to your cart. Please try again.",
                'data' => array('action' => 'error')
            );
        }
    }
    
    private function handleQuickReorder($customer_id, $query) {
        try {
            if (!function_exists('wc_get_orders')) {
                return array(
                    'response' => "I'm having trouble accessing your order history. Please try browsing our products instead.",
                    'data' => array('action' => 'error')
                );
            }
            
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'limit' => 1,
                'status' => 'completed'
            ));
            
            if(empty($orders)) {
                return array(
                    'response' => "I couldn't find any previous orders to reorder. Would you like to browse our products instead?",
                    'data' => array(
                        'action' => 'no_previous_orders',
                        'suggestions' => $this->getProductRecommendations($customer_id, 5)
                    )
                );
            }
            
            $last_order = $orders[0];
            $line_items = array();
            $total_amount = 0;
            
            foreach($last_order->get_items() as $item) {
                $product = $item->get_product();
                
                if($product && $product->get_status() === 'publish') {
                    $line_items[] = array(
                        'product_id' => $item->get_product_id(),
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity()
                    );
                    $total_amount += floatval($product->get_price()) * $item->get_quantity();
                }
            }
            
            if(empty($line_items)) {
                return array(
                    'response' => "Unfortunately, none of the products from your last order are currently available.",
                    'data' => array(
                        'action' => 'products_unavailable',
                        'suggestions' => $this->getProductRecommendations($customer_id, 5)
                    )
                );
            }
            
            // Simulate order creation for reorder
            $order_id = rand(1000, 9999);
            $checkout_url = wc_get_checkout_url() . "?reorder=" . $order_id;
            
            return array(
                'response' => "Perfect! I've recreated your previous order with a total of $" . number_format($total_amount, 2) . ". Click below to complete your purchase.",
                'data' => array(
                    'action' => 'redirect_to_checkout',
                    'order_id' => $order_id,
                    'checkout_url' => $checkout_url,
                    'total_amount' => $total_amount,
                    'original_order' => $last_order->get_order_number()
                )
            );
            
        } catch(Exception $e) {
            error_log("Quick reorder error: " . $e->getMessage());
            return array(
                'response' => "I'm having trouble processing your reorder. Please try again later.",
                'data' => array('action' => 'error')
            );
        }
    }
    
    /**
     * Utility methods for product extraction and search
     */
    private function extractProductsFromQuery($query, $context = array()) {
        $products = array();
        
        if(isset($context['products']) && !empty($context['products'])) {
            return $context['products'];
        }
        
        $query_lower = strtolower($query);
        
        // Look for quantity patterns
        preg_match_all('/(\d+)\s*[x*]?\s*([a-zA-Z\s]+)/', $query_lower, $matches);
        
        if(!empty($matches[0])) {
            for($i = 0; $i < count($matches[1]); $i++) {
                $quantity = intval($matches[1][$i]);
                $product_name = trim($matches[2][$i]);
                
                $found_products = $this->searchProductsByName($product_name);
                
                if(!empty($found_products)) {
                    $products[] = array(
                        'id' => $found_products[0]->get_id(),
                        'name' => $found_products[0]->get_name(),
                        'quantity' => $quantity
                    );
                }
            }
        } else {
            $words = explode(' ', $query_lower);
            $stop_words = array('i', 'want', 'to', 'buy', 'purchase', 'get', 'order', 'the', 'a', 'an');
            $filtered_words = array_diff($words, $stop_words);
            
            if(!empty($filtered_words)) {
                $search_term = implode(' ', $filtered_words);
                $found_products = $this->searchProductsByName($search_term);
                
                foreach(array_slice($found_products, 0, 3) as $product) {
                    $products[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'quantity' => 1
                    );
                }
            }
        }
        
        return $products;
    }
    
    private function searchProductsByQuery($query) {
        return $this->searchProductsByName($query);
    }
    
    private function searchProductsByName($search_term) {
        if (!function_exists('wc_get_products')) {
            return array();
        }
        
        return wc_get_products(array(
            's' => $search_term,
            'limit' => 5,
            'status' => 'publish'
        ));
    }
    
    private function getCustomerContext($customer_id) {
        try {
            if (!function_exists('get_userdata') || !function_exists('wc_get_orders')) {
                return array();
            }
            
            $user = get_userdata($customer_id);
            if (!$user) {
                return array();
            }
            
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'limit' => 10
            ));
            
            $total_orders = count($orders);
            $total_spent = 0;
            $favorite_categories = array();
            
            foreach($orders as $order) {
                $total_spent += floatval($order->get_total());
                
                foreach($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $categories = wp_get_post_terms($product_id, 'product_cat');
                    
                    foreach($categories as $cat) {
                        $favorite_categories[$cat->name] = 
                            ($favorite_categories[$cat->name] ?? 0) + 1;
                    }
                }
            }
            
            arsort($favorite_categories);
            
            return array(
                'customer_info' => array(
                    'name' => $user->first_name ?: $user->display_name,
                    'total_orders' => $total_orders,
                    'total_spent' => $total_spent,
                    'favorite_categories' => array_keys(array_slice($favorite_categories, 0, 3)),
                    'is_returning_customer' => $total_orders > 1
                ),
                'store_info' => array(
                    'has_support_team' => true,
                    'return_policy_days' => 30,
                    'free_shipping_threshold' => 50
                )
            );
            
        } catch(Exception $e) {
            return array();
        }
    }
    
    private function getFallbackResponse($query) {
        $responses = array(
            "Thank you for contacting us! I'll do my best to help you with your inquiry.",
            "I understand your concern. Let me provide you with the information you need.",
            "I'm here to help! Based on your question, let me assist you with that.",
            "Thanks for reaching out. I'll make sure you get the assistance you need."
        );
        
        return $responses[array_rand($responses)];
    }
    
    private function logInteraction($customer_id, $type, $message, $response, $sentiment) {
        if($this->db) {
            $table_name = $this->db->prefix . 'wc_ai_chat_conversations';
            
            $this->db->insert(
                $table_name,
                array(
                    'customer_id' => $customer_id,
                    'message' => $message,
                    'response' => wp_json_encode(array(
                        'response' => $response,
                        'type' => $type,
                        'sentiment' => $sentiment
                    )),
                    'timestamp' => current_time('mysql'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Inventory Management AI
     */
    public function analyzeInventory() {
        try {
            if (!function_exists('wc_get_products')) {
                return array();
            }
            
            $products = wc_get_products(array(
                'limit' => 100,
                'status' => 'publish',
                'manage_stock' => true
            ));
            
            $insights = array();
            
            foreach($products as $product) {
                if($product->managing_stock()) {
                    $stock_quantity = $product->get_stock_quantity();
                    $sales_data = $this->getProductSalesData($product->get_id());
                    
                    $prediction = $this->predictStockOut($product->get_id(), $stock_quantity, $sales_data);
                    
                    if($prediction['days_until_stockout'] < 7) {
                        $insights[] = array(
                            'type' => 'low_stock_alert',
                            'product_id' => $product->get_id(),
                            'product_name' => $product->get_name(),
                            'current_stock' => $stock_quantity,
                            'predicted_stockout' => $prediction['days_until_stockout'],
                            'recommended_reorder' => $prediction['recommended_quantity'],
                            'priority' => $stock_quantity < 5 ? 'high' : 'medium'
                        );
                    }
                }
            }
            
            $this->logInsight('inventory_analysis', $insights);
            return $insights;
            
        } catch(Exception $e) {
            error_log("Inventory analysis error: " . $e->getMessage());
            return array();
        }
    }
    
    private function getProductSalesData($product_id, $days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $end_date = date('Y-m-d');
        
        $sales_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(posts.post_date) as sale_date,
                SUM(order_itemmeta.meta_value) as quantity_sold
            FROM {$wpdb->prefix}posts as posts
            INNER JOIN {$wpdb->prefix}woocommerce_order_items as order_items 
                ON posts.ID = order_items.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_itemmeta 
                ON order_items.order_item_id = order_itemmeta.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as product_meta 
                ON order_items.order_item_id = product_meta.order_item_id
            WHERE posts.post_type = 'shop_order'
                AND posts.post_status IN ('wc-processing', 'wc-completed')
                AND posts.post_date >= %s
                AND posts.post_date <= %s
                AND order_itemmeta.meta_key = '_qty'
                AND product_meta.meta_key = '_product_id'
                AND product_meta.meta_value = %d
            GROUP BY DATE(posts.post_date)
            ORDER BY sale_date DESC
        ", $start_date, $end_date, $product_id));
        
        $sales_by_day = array();
        foreach($sales_data as $sale) {
            $sales_by_day[] = intval($sale->quantity_sold);
        }
        
        return $sales_by_day;
    }
    
    private function predictStockOut($product_id, $current_stock, $sales_data) {
        $total_sales = array_sum($sales_data);
        $days_of_data = count($sales_data);
        $average_daily_sales = $days_of_data > 0 ? $total_sales / $days_of_data : 1;
        
        $days_until_stockout = $average_daily_sales > 0 ? $current_stock / $average_daily_sales : 999;
        $recommended_quantity = ceil($average_daily_sales * 30);
        
        return array(
            'days_until_stockout' => round($days_until_stockout),
            'recommended_quantity' => max($recommended_quantity, 10),
            'average_daily_sales' => round($average_daily_sales, 2)
        );
    }
    
    /**
     * Sales Analytics and Insights
     */
    public function generateSalesInsights($days = 30) {
        try {
            if (!function_exists('wc_get_orders')) {
                return array();
            }
            
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime("-{$days} days"));
            
            $orders = wc_get_orders(array(
                'date_created' => $start_date . '...' . $end_date,
                'status' => array('completed', 'processing'),
                'limit' => -1
            ));
            
            $insights = array(
                'total_revenue' => 0,
                'total_orders' => count($orders),
                'average_order_value' => 0,
                'top_products' => array(),
                'customer_segments' => array(
                    'high_value' => 0,
                    'frequent' => 0,
                    'regular' => 0
                ),
                'trends' => array()
            );
            
            $product_sales = array();
            $customer_data = array();
            
            foreach($orders as $order) {
                $insights['total_revenue'] += floatval($order->get_total());
                
                foreach($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    if(!isset($product_sales[$product_id])) {
                        $product_sales[$product_id] = array(
                            'name' => $item->get_name(),
                            'quantity' => 0,
                            'revenue' => 0
                        );
                    }
                    $product_sales[$product_id]['quantity'] += $item->get_quantity();
                    $product_sales[$product_id]['revenue'] += floatval($item->get_total());
                }
                
                $customer_id = $order->get_customer_id();
                if($customer_id > 0) {
                    if(!isset($customer_data[$customer_id])) {
                        $customer_data[$customer_id] = array(
                            'orders' => 0,
                            'total_spent' => 0
                        );
                    }
                    $customer_data[$customer_id]['orders']++;
                    $customer_data[$customer_id]['total_spent'] += floatval($order->get_total());
                }
            }
            
            $insights['average_order_value'] = $insights['total_orders'] > 0 ? 
                $insights['total_revenue'] / $insights['total_orders'] : 0;
            
            uasort($product_sales, function($a, $b) {
                return $b['revenue'] - $a['revenue'];
            });
            $insights['top_products'] = array_slice($product_sales, 0, 5, true);
            
            foreach($customer_data as $customer_id => $data) {
                if($data['total_spent'] > 1000) {
                    $insights['customer_segments']['high_value']++;
                } elseif($data['orders'] > 5) {
                    $insights['customer_segments']['frequent']++;
                } else {
                    $insights['customer_segments']['regular']++;
                }
            }
            
            $this->logInsight('sales_analysis', $insights);
            return $insights;
            
        } catch(Exception $e) {
            error_log("Sales insights error: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Automated Marketing Campaigns
     */
    public function createPersonalizedCampaigns() {
        try {
            if (!function_exists('get_users')) {
                return array();
            }
            
            $customers = get_users(array(
                'role' => 'customer',
                'number' => 100
            ));
            
            $campaigns = array();
            
            foreach($customers as $customer) {
                $customer_id = $customer->ID;
                $last_order_date = $this->getCustomerLastOrderDate($customer_id);
                $days_since_last_order = $this->getDaysSince($last_order_date);
                
                if($days_since_last_order > 60 && $days_since_last_order < 180) {
                    $recommendations = $this->getProductRecommendations($customer_id, 3);
                    $campaigns[] = array(
                        'type' => 'win_back',
                        'customer_id' => $customer_id,
                        'customer_email' => $customer->user_email,
                        'customer_name' => $customer->first_name ?: $customer->display_name,
                        'subject' => "We miss you! Here's 15% off your next order",
                        'recommendations' => $recommendations,
                        'discount' => 15
                    );
                }
                
                if($this->isCustomerBirthday($customer)) {
                    $campaigns[] = array(
                        'type' => 'birthday',
                        'customer_id' => $customer_id,
                        'customer_email' => $customer->user_email,
                        'customer_name' => $customer->first_name ?: $customer->display_name,
                        'subject' => "Happy Birthday! Enjoy 20% off",
                        'discount' => 20
                    );
                }
                
                $replenishment_products = $this->getReplenishmentProducts($customer_id);
                if(!empty($replenishment_products)) {
                    $campaigns[] = array(
                        'type' => 'replenishment',
                        'customer_id' => $customer_id,
                        'customer_email' => $customer->user_email,
                        'customer_name' => $customer->first_name ?: $customer->display_name,
                        'subject' => "Time to restock your favorites!",
                        'products' => $replenishment_products
                    );
                }
            }
            
            return $campaigns;
            
        } catch(Exception $e) {
            error_log("Campaign creation error: " . $e->getMessage());
            return array();
        }
    }
    
    private function getCustomerLastOrderDate($customer_id) {
        if (!function_exists('wc_get_orders')) {
            return null;
        }
        
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => 1,
            'status' => 'completed'
        ));
        
        if (!empty($orders)) {
            return $orders[0]->get_date_created();
        }
        
        return null;
    }
    
    private function getDaysSince($date) {
        if (!$date) {
            return 999;
        }
        
        $now = new DateTime();
        $order_date = is_string($date) ? new DateTime($date) : $date;
        $diff = $now->diff($order_date);
        
        return $diff->days;
    }
    
    private function isCustomerBirthday($customer) {
        $birth_date = get_user_meta($customer->ID, 'billing_birth_date', true);
        if (!$birth_date) {
            return false;
        }
        
        $today = date('m-d');
        $customer_birthday = date('m-d', strtotime($birth_date));
        
        return $today === $customer_birthday;
    }
    
    private function getReplenishmentProducts($customer_id) {
        if (!function_exists('wc_get_orders')) {
            return array();
        }
        
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => 5,
            'status' => 'completed',
            'date_created' => '>' . (time() - (60 * DAY_IN_SECONDS)) // Last 60 days
        ));
        
        $product_frequency = array();
        
        foreach($orders as $order) {
            foreach($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product = $item->get_product();
                
                if ($product && $this->isReplenishableProduct($product)) {
                    if (!isset($product_frequency[$product_id])) {
                        $product_frequency[$product_id] = array(
                            'name' => $item->get_name(),
                            'count' => 0,
                            'last_ordered' => $order->get_date_created()
                        );
                    }
                    $product_frequency[$product_id]['count']++;
                }
            }
        }
        
        $replenishment_products = array();
        foreach($product_frequency as $product_id => $data) {
            if ($data['count'] >= 2) { // Ordered at least twice
                $days_since_last = $this->getDaysSince($data['last_ordered']);
                if ($days_since_last >= 30) { // Last ordered 30+ days ago
                    $replenishment_products[] = array(
                        'product_id' => $product_id,
                        'name' => $data['name'],
                        'frequency' => $data['count']
                    );
                }
            }
        }
        
        return $replenishment_products;
    }
    
    private function isReplenishableProduct($product) {
        $replenishable_categories = array('consumables', 'food', 'health', 'beauty', 'supplements');
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
        
        return !empty(array_intersect($product_categories, $replenishable_categories));
    }
    
    private function logInsight($type, $data) {
        if($this->db) {
            $table_name = $this->db->prefix . 'wc_ai_chat_analytics';
            
            $this->db->insert(
                $table_name,
                array(
                    'event_type' => $type,
                    'data' => wp_json_encode($data),
                    'timestamp' => current_time('mysql')
                ),
                array('%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Main execution method for automated tasks
     */
    public function run() {
        echo "WooCommerce AI Agent Running...\n";
        
        try {
            // Run inventory analysis
            $inventory_insights = $this->analyzeInventory();
            if(!empty($inventory_insights)) {
                echo "Found " . count($inventory_insights) . " inventory alerts\n";
                $this->processInventoryAlerts($inventory_insights);
            }
            
            // Generate sales insights
            $sales_insights = $this->generateSalesInsights();
            echo "Generated sales insights for " . $sales_insights['total_orders'] . " orders\n";
            
            // Create marketing campaigns
            $campaigns = $this->createPersonalizedCampaigns();
            if(!empty($campaigns)) {
                echo "Created " . count($campaigns) . " personalized campaigns\n";
                $this->processCampaigns($campaigns);
            }
            
            // Cleanup old data
            $this->cleanupOldData();
            
            echo "AI Agent cycle completed.\n";
            
        } catch(Exception $e) {
            error_log("AI Agent execution error: " . $e->getMessage());
            echo "Error during AI Agent execution: " . $e->getMessage() . "\n";
        }
    }
    
    private function processInventoryAlerts($alerts) {
        foreach($alerts as $alert) {
            if ($alert['priority'] === 'high') {
                // Send urgent email to admin
                $this->sendInventoryAlert($alert);
            }
            
            // Log for dashboard
            $this->logInsight('inventory_alert', $alert);
        }
    }
    
    private function processCampaigns($campaigns) {
        foreach($campaigns as $campaign) {
            // In production, integrate with email marketing service
            $this->logInsight('campaign_created', $campaign);
            
            // Send campaign (mock implementation)
            if ($campaign['type'] === 'birthday') {
                $this->sendBirthdayEmail($campaign);
            } elseif ($campaign['type'] === 'win_back') {
                $this->sendWinBackEmail($campaign);
            }
        }
    }
    
    private function sendInventoryAlert($alert) {
        $admin_email = get_option('admin_email');
        $subject = "Low Stock Alert: " . $alert['product_name'];
        
        $message = "
        <h2>Low Stock Alert</h2>
        <p><strong>Product:</strong> {$alert['product_name']}</p>
        <p><strong>Current Stock:</strong> {$alert['current_stock']} units</p>
        <p><strong>Predicted Stock-out:</strong> {$alert['predicted_stockout']} days</p>
        <p><strong>Recommended Reorder:</strong> {$alert['recommended_reorder']} units</p>
        <p><strong>Priority:</strong> {$alert['priority']}</p>
        ";
        
        wp_mail($admin_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    private function sendBirthdayEmail($campaign) {
        // Mock birthday email implementation
        $this->logInsight('email_sent', array(
            'type' => 'birthday',
            'customer_id' => $campaign['customer_id'],
            'discount' => $campaign['discount']
        ));
    }
    
    private function sendWinBackEmail($campaign) {
        // Mock win-back email implementation
        $this->logInsight('email_sent', array(
            'type' => 'win_back',
            'customer_id' => $campaign['customer_id'],
            'recommendations_count' => count($campaign['recommendations'])
        ));
    }
    
    private function cleanupOldData() {
        // Clean up old conversation logs (older than 90 days)
        $this->db->query($this->db->prepare("
            DELETE FROM {$this->db->prefix}wc_ai_chat_conversations 
            WHERE timestamp < %s
        ", date('Y-m-d H:i:s', strtotime('-90 days'))));
        
        // Clean up old analytics data (older than 1 year)
        $this->db->query($this->db->prepare("
            DELETE FROM {$this->db->prefix}wc_ai_chat_analytics 
            WHERE timestamp < %s
        ", date('Y-m-d H:i:s', strtotime('-1 year'))));
    }
    
    /**
     * Get conversation statistics
     */
    public function getConversationStats($days = 30) {
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Total conversations
        $stats['total_conversations'] = $this->db->get_var($this->db->prepare("
            SELECT COUNT(*) FROM {$this->db->prefix}wc_ai_chat_conversations 
            WHERE timestamp >= %s
        ", $start_date));
        
        // Unique customers
        $stats['unique_customers'] = $this->db->get_var($this->db->prepare("
            SELECT COUNT(DISTINCT customer_id) FROM {$this->db->prefix}wc_ai_chat_conversations 
            WHERE timestamp >= %s
        ", $start_date));
        
        // Sentiment breakdown
        $sentiment_stats = $this->db->get_results($this->db->prepare("
            SELECT 
                JSON_EXTRACT(response, '$.sentiment') as sentiment,
                COUNT(*) as count
            FROM {$this->db->prefix}wc_ai_chat_conversations 
            WHERE timestamp >= %s
            GROUP BY JSON_EXTRACT(response, '$.sentiment')
        ", $start_date));
        
        $stats['sentiment'] = array();
        foreach($sentiment_stats as $stat) {
            $sentiment = trim($stat->sentiment, '"');
            $stats['sentiment'][$sentiment] = intval($stat->count);
        }
        
        // Query type breakdown
        $query_types = $this->db->get_results($this->db->prepare("
            SELECT 
                JSON_EXTRACT(response, '$.type') as query_type,
                COUNT(*) as count
            FROM {$this->db->prefix}wc_ai_chat_conversations 
            WHERE timestamp >= %s
            GROUP BY JSON_EXTRACT(response, '$.type')
        ", $start_date));
        
        $stats['query_types'] = array();
        foreach($query_types as $type) {
            $query_type = trim($type->query_type, '"');
            $stats['query_types'][$query_type] = intval($type->count);
        }
        
        return $stats;
    }
    
    /**
     * Get recent conversations for admin
     */
    public function getRecentConversations($limit = 20) {
        return $this->db->get_results($this->db->prepare("
            SELECT * FROM {$this->db->prefix}wc_ai_chat_conversations 
            ORDER BY timestamp DESC 
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get customer conversation history
     */
    public function getCustomerHistory($customer_id, $limit = 50) {
        return $this->db->get_results($this->db->prepare("
            SELECT * FROM {$this->db->prefix}wc_ai_chat_conversations 
            WHERE customer_id = %s
            ORDER BY timestamp DESC 
            LIMIT %d
        ", $customer_id, $limit));
    }
}

// WooCommerce API Client fallback for external API usage
if (!class_exists('WC_API_Client')) {
    class WC_API_Client {
        private $store_url;
        private $api_key;
        private $api_secret;
        
        public function __construct($store_url, $api_key, $api_secret, $options = array()) {
            $this->store_url = rtrim($store_url, '/');
            $this->api_key = $api_key;
            $this->api_secret = $api_secret;
        }
        
        public function get($endpoint, $params = array()) {
            return $this->makeRequest('GET', $endpoint, $params);
        }
        
        public function post($endpoint, $data = array()) {
            return $this->makeRequest('POST', $endpoint, $data);
        }
        
        private function makeRequest($method, $endpoint, $data = array()) {
            $url = $this->store_url . '/wp-json/wc/v3/' . ltrim($endpoint, '/');
            
            $args = array(
                'method' => $method,
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret),
                    'Content-Type' => 'application/json'
                )
            );
            
            if ($method === 'POST' && !empty($data)) {
                $args['body'] = json_encode($data);
            } elseif ($method === 'GET' && !empty($data)) {
                $url = add_query_arg($data, $url);
            }
            
            $response = wp_remote_request($url, $args);
            
            if (is_wp_error($response)) {
                error_log('WC API Error: ' . $response->get_error_message());
                return array();
            }
            
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
            
            return $decoded ?: array();
        }
    }
}

?>