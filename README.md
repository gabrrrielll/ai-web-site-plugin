# AI Web Site - WordPress Plugin

WordPress plugin for AI Website Builder with subdomain management via cPanel API.

## 🔌 Plugin Features

### 🏗️ Subdomain Management
- **Create subdomains** automatically via cPanel API
- **Delete subdomains** with confirmation
- **List existing subdomains** with management options
- **Configure subdomain settings** (directory, SSL, etc.)

### 📊 Site Configuration
- **Store site configurations** per subdomain
- **JSON-based config management** in WordPress database
- **REST API endpoints** for frontend integration
- **Admin interface** for configuration management

### ⚙️ Admin Interface
- **User-friendly dashboard** for subdomain management
- **API key configuration** (secure storage)
- **Real-time status updates** and error handling
- **Bulk operations** for multiple subdomains

## 📋 Installation

### Method 1: Git Clone (Recommended)

1. **Clone to WordPress plugins directory**:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/gabrrrielll/ai-web-site-backend.git ai-web-site
   ```

2. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "AI Web Site" plugin
   - Click "Activate"

### Method 2: Manual Upload

1. **Download plugin files**:
   - Download ZIP from GitHub
   - Extract to `wp-content/plugins/ai-web-site/`

2. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "AI Web Site" plugin
   - Click "Activate"

## 🔧 Configuration

### Required Setup

1. **Go to AI Web Site → Settings** in WordPress Admin
2. **Configure cPanel API** (only 3 fields needed):
   - **cPanel Username**: Your cPanel username (e.g., `r48312maga`)
   - **cPanel API Token**: Your cPanel API token (e.g., `JACSKFOEX1D40JJL8UFY28ADKUXA3M9G`)
   - **Main Domain**: Your main domain (e.g., `ai-web.site`)

### API Configuration

The plugin handles:
- **Subdomain creation** via cPanel API
- **Database storage** for site configurations
- **REST API endpoints** for frontend communication
- **Security validation** for all operations

## 📡 REST API Endpoints

Base namespace: `/wp-json/ai-web-site/v1`

| Route | Method | Description |
|---|---|---|
| `/website-config/{domain}` | GET | Load website configuration by domain |
| `/website-config` | POST | Save website configuration |
| `/website/{domain}` | GET | Backward-compatibility endpoint for reading configuration |
| `/wp-nonce` | GET | Return WordPress nonce used for authentication |
| `/user-site/add-subdomain` | POST | Add subdomain for a user website |
| `/user-site/delete` | POST | Delete user website |
| `/ai/generate-text` | POST | Generate text using configured AI provider |
| `/ai/generate-image` | POST | Generate image using configured AI provider |
| `/site-config` | GET | Return site configuration by `subdomain` parameter |
| `/logs` | GET | Return debugging logs |

### 🧪 Postman Testing

Import these files in Postman:

- Collection: `/postman/ai-web-site-plugin.postman_collection.json`
- Environment: `/postman/ai-web-site-plugin.postman_environment.json`

After import:

1. Select the imported environment in Postman.
2. Set `base_url` to your site URL (example: `https://your-domain.com/wp-json/ai-web-site/v1`).
3. Run `GET /wp-nonce` (while authenticated in WordPress) and copy the returned nonce to `wp_nonce` environment variable.
4. Run the remaining requests from the collection.

## 🔄 Auto-Update

This repository is automatically updated when:
1. Plugin improvements are made
2. New features are added
3. Bug fixes are implemented

## 📝 Plugin Structure

```
ai-web-site/
├── ai-web-site.php              # Plugin header and initialization
├── includes/
│   ├── class-ai-web-site.php    # Main plugin class
│   ├── class-cpanel-api.php     # cPanel API integration
│   └── class-database.php       # Database operations
├── admin/
│   ├── class-admin.php          # Admin interface class
│   └── admin-page.php           # Admin page template
└── assets/
    └── admin.js                 # Admin interface JavaScript
```

## 🛡️ Security Features

- **WordPress nonce verification** for all admin actions
- **User capability checks** (admin only)
- **Input sanitization** and validation
- **Secure API key storage** in WordPress options
- **CSRF protection** on all forms

## 🚀 Usage

### Creating a Subdomain

1. Go to **AI Web Site → Subdomains** in WordPress Admin
2. Click **"Add New Subdomain"**
3. Enter subdomain name (e.g., `example`)
4. Configure settings (directory, SSL, etc.)
5. Click **"Create Subdomain"**

### Managing Site Configurations

1. Go to **AI Web Site → Site Configs**
2. Select subdomain from dropdown
3. Edit JSON configuration
4. Click **"Save Configuration"**

## 🔧 Development

### Local Development

```bash
# Clone the plugin
git clone https://github.com/gabrrrielll/ai-web-site-backend.git

# Make changes to plugin files
# Test in local WordPress installation

# Deploy changes
npm run deploy:backend
```

### Plugin Hooks

The plugin provides WordPress hooks for customization:
- `ai_web_site_before_subdomain_create` - Before subdomain creation
- `ai_web_site_after_subdomain_create` - After subdomain creation
- `ai_web_site_config_save` - When site config is saved

## 📞 Support

For issues or questions:
1. Check WordPress error logs
2. Verify cPanel API token and permissions
3. Review plugin settings in WordPress Admin
4. Check GitHub issues for known problems

---

**Plugin Version**: 1.0.0  
**WordPress Compatibility**: 5.0+  
**PHP Requirements**: 7.4+
