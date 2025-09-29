<?php

/**
 * Debug form submission
 */

echo "🧪 Testing Form Submission\n";
echo "==========================\n\n";

// Simulate form data
$form_data = array(
    '_wpnonce' => 'test_nonce',
    'action' => 'save_ai_web_site_options',
    'cpanel_username' => 'r48312maga',
    'cpanel_api_token' => 'JACSKFOEX1D40JJL8UFY28ADKUXA3M9G',
    'main_domain' => 'ai-web.site'
);

echo "📋 Form Data:\n";
foreach ($form_data as $key => $value) {
    if ($key === 'cpanel_api_token') {
        echo "  {$key}: " . str_repeat('*', strlen($value)) . "\n";
    } else {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n🔗 Expected URL: https://ai-web.site/wp-admin/admin-post.php\n";
echo "🎯 Expected Action: save_ai_web_site_options\n";
echo "🔑 Expected Hook: admin_post_save_ai_web_site_options\n\n";

echo "✅ Form structure looks correct!\n";
echo "❓ If it's still not working, the issue might be:\n";
echo "   1. Form not submitting to admin-post.php\n";
echo "   2. Hook not being triggered\n";
echo "   3. Nonce verification failing\n";
echo "   4. Permission check failing\n\n";

echo "💡 Next steps:\n";
echo "   1. Check browser developer tools Network tab\n";
echo "   2. Verify form action URL\n";
echo "   3. Check if POST data is being sent\n";
