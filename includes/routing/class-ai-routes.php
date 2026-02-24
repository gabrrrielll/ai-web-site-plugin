<?php
/**
 * AI API Routes
 * 
 * Handles proxied AI requests to providers like Gemini and DeepSeek.
 * 
 * @package AI_Web_Site_Plugin
 * @subpackage Routing
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Routes class
 */
class AI_Web_Site_AI_Routes extends AI_Web_Site_Base_Routes {
    
    /**
     * Get routes
     */
    public function get_routes() {
        return array(
            '/ai/generate-text' => array(
                'methods' => 'POST',
                'callback' => array($this, 'generate_text'),
                'permission_callback' => array($this, 'check_authenticated_permission'),
                'args' => array(
                    'prompt' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ),
                    'format' => array(
                        'required' => false,
                        'type' => 'string',
                        'default' => 'text',
                        'enum' => array('text', 'json')
                    ),
                    'provider' => array(
                        'required' => false,
                        'type' => 'string',
                        'default' => 'gemini',
                        'enum' => array('gemini', 'deepseek')
                    )
                )
            ),
            '/ai/model-limits' => array(
                'methods' => 'GET',
                'callback' => array($this, 'get_model_limits'),
                'permission_callback' => array($this, 'check_authenticated_permission'),
            ),
            '/ai/generate-image' => array(
                'methods' => 'POST',
                'callback' => array($this, 'generate_image'),
                'permission_callback' => array($this, 'check_authenticated_permission'),
                'args' => array(
                    'prompt' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ),
                    'aspect_ratio' => array(
                        'required' => false,
                        'type' => 'string',
                        'default' => '16:9'
                    )
                )
            )
        );
    }
    
    /**
     * Generate text
     */
    public function generate_text($request) {
        $params = $request->get_params();
        $prompt = $params['prompt'];
        $format = $params['format'];
        $provider = $params['provider'];
        
        $options = get_option('ai_web_site_options', array());
        $api_key = '';
        
        if ($provider === 'deepseek') {
            $api_key = $options['ai_deepseek_api_key'] ?? '';
            if (empty($api_key)) {
                // Fallback to Gemini if DeepSeek is not configured
                $provider = 'gemini';
                $api_key = $options['ai_gemini_api_key'] ?? '';
            }
        } else {
            $api_key = $options['ai_gemini_api_key'] ?? '';
        }

        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'AI API Key is not configured in settings', array('status' => 500));
        }
        
        if ($provider === 'gemini') {
            $gemini_model = $options['ai_gemini_model'] ?? 'models/gemini-1.5-flash';
            $gemini_output_limit = isset($options['ai_gemini_output_token_limit']) ? (int) $options['ai_gemini_output_token_limit'] : 0;
            return $this->call_gemini_text($api_key, $prompt, $format, $gemini_model, $gemini_output_limit);
        } else {
            // Placeholder for DeepSeek implementation
            return new WP_Error('not_implemented', 'DeepSeek provider not fully implemented yet', array('status' => 501));
        }
    }

    /**
     * Return selected Gemini model limits saved in plugin options.
     */
    public function get_model_limits($request) {
        $options = get_option('ai_web_site_options', array());
        $model = $options['ai_gemini_model'] ?? 'models/gemini-1.5-flash';
        $input_limit = isset($options['ai_gemini_input_token_limit']) ? (int) $options['ai_gemini_input_token_limit'] : 1048576;
        $output_limit = isset($options['ai_gemini_output_token_limit']) ? (int) $options['ai_gemini_output_token_limit'] : 2048;

        // Keep a healthy headroom for model/system/output overhead.
        $recommended_prompt_input_tokens = max(8192, (int) floor($input_limit * 0.55));

        return array(
            'success' => true,
            'provider' => 'gemini',
            'model' => $model,
            'inputTokenLimit' => $input_limit,
            'outputTokenLimit' => $output_limit,
            'recommendedPromptInputTokens' => $recommended_prompt_input_tokens,
        );
    }

    /**
     * Generate image (Stub for future implementation or redirect to specialized service)
     */
    public function generate_image($request) {
         // Current implementation in geminiService calls /api/gemini with type=image
         // We can implement Gemini Image generation here
         
         $params = $request->get_params();
         $prompt = $params['prompt'];
         $aspect_ratio = $params['aspect_ratio'];
         
         $options = get_option('ai_web_site_options', array());
         $api_key = $options['ai_gemini_api_key'] ?? '';
         
         if (empty($api_key)) {
             return new WP_Error('missing_api_key', 'Gemini API Key is not configured', array('status' => 500));
         }

         return $this->call_gemini_image($api_key, $prompt, $aspect_ratio);
    }

    /**
     * Call Gemini API for text
     */
    private function call_gemini_text($api_key, $prompt, $format, $model_name, $output_token_limit = 0) {
        $model_name = sanitize_text_field((string) $model_name);
        if (empty($model_name) || !preg_match('/^models\/[a-zA-Z0-9._-]+$/', $model_name)) {
            $model_name = 'models/gemini-1.5-flash';
        }

        $output_token_limit = (int) $output_token_limit;
        if ($output_token_limit <= 0) {
            $output_token_limit = 2048;
        }

        $token_check = $this->count_gemini_input_tokens($api_key, $model_name, $prompt);
        if (is_wp_error($token_check)) {
            return $token_check;
        }

        $input_tokens = (int) ($token_check['inputTokens'] ?? 0);
        $input_limit = (int) ($token_check['inputTokenLimit'] ?? 0);
        if ($input_limit > 0 && $input_tokens > $input_limit) {
            return new WP_Error(
                'input_token_limit_exceeded',
                'The input token count exceeds the maximum number of tokens allowed ' . $input_limit . '.',
                array(
                    'status' => 400,
                    'inputTokens' => $input_tokens,
                    'inputTokenLimit' => $input_limit,
                    'model' => $model_name,
                )
            );
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/' . $model_name . ':generateContent?key=' . $api_key;
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => $output_token_limit,
            )
        );

        if ($format === 'json') {
             // Gemini specific json mode isn't always strictly enforced via MIME type in v1beta without specific models, 
             // but we can prompt engineer or use response_mime_type if using gemini-1.5-pro or similar.
             // For gemini-pro, we stick to standard text and rely on prompt engineering or basic config.
             // However, let's try to pass the mime type if supported or just ensuring the system prompt requests JSON.
             // Note: 'response_mime_type': 'application/json' is supported in newer models.
             // We'll stick to a safe default for now or add it.
             // Let's assuming gemini-1.5-flash or pro usage for JSON, but let's stick to simple text for compatibility
             // unless we change model.
        }

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['error'])) {
             return new WP_Error('gemini_error', $data['error']['message'] ?? 'Unknown Gemini Error', array('status' => 400));
        }

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('gemini_invalid_response', 'Invalid response format from Gemini', array('status' => 502, 'data' => $data));
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'];

        return array(
            'success' => true,
            'text' => $text,
            'provider' => 'gemini',
            'tokenUsage' => array(
                'inputTokens' => $input_tokens,
                'inputTokenLimit' => $input_limit,
            ),
        );
    }

    /**
     * Preflight token count to avoid expensive generateContent failures.
     */
    private function count_gemini_input_tokens($api_key, $model_name, $prompt) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/' . $model_name . ':countTokens?key=' . $api_key;
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        if (isset($data['error'])) {
            return new WP_Error('gemini_count_tokens_error', $data['error']['message'] ?? 'Failed to count tokens', array('status' => 400));
        }

        $total_tokens = isset($data['totalTokens']) ? (int) $data['totalTokens'] : 0;
        $input_limit = 0;

        if (isset($data['model']['inputTokenLimit'])) {
            $input_limit = (int) $data['model']['inputTokenLimit'];
        } elseif (isset($data['inputTokenLimit'])) {
            $input_limit = (int) $data['inputTokenLimit'];
        }

        $options = get_option('ai_web_site_options', array());
        if ($input_limit <= 0 && isset($options['ai_gemini_input_token_limit'])) {
            $input_limit = (int) $options['ai_gemini_input_token_limit'];
        }

        return array(
            'inputTokens' => $total_tokens,
            'inputTokenLimit' => $input_limit,
        );
    }

    /**
     * Call Gemini API for Image
     */
    private function call_gemini_image($api_key, $prompt, $aspect_ratio) {
        // NOTE: Gemini Pro Vision is for inputting images. Gemini usually doesn't generate images directly via this API in all regions/models yet 
        // without specific Imagen integration or similar. 
        // However, assuming the user had this working in their JS code via some endpoint, we replicate expectation.
        // If the JS code was mocking it or using a specific other endpoint, we need to know.
        // The read JS code used: type: 'image', outputMimeType: 'image/jpeg'.
        // This looks like Vertex AI or a specific wrapper. 
        // Standard Gemini Consumer API (generativelanguage) might not support image generation directly broadly yet (Imagen 2/3 is coming).
        // I will implement a placeholder error or assume they have access to a model that supports it.
        // Safe bet: Return error saying "Image generation proxy not fully configured" or try to implement if known.
        
        return new WP_Error('not_implemented', 'Image generation via Gemini API not yet supported in this proxy version.', array('status' => 501));
    }
}
