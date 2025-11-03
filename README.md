# BAGO Home Decor Catalog 🏠

A modern, responsive furniture catalog built with vanilla HTML, CSS, and JavaScript. Features optimized font loading, WebP image support, real-time shopping cart, and administrative management tools.

## ✨ Features

- **📱 Responsive Design** - Works perfectly on desktop, tablet, and mobile
- **🛒 Shopping Cart** - Real-time cart with PDF checkout generation
- **🎨 Modern UI** - Clean design with Inter font and Tailwind CSS
- **🔍 Advanced Search** - Filter by category, search products, sort by price/name
- **⚡ Performance Optimized** - Preloaded fonts, WebP images, lazy loading
- **🖼️ Image Optimization** - Automated WebP conversion with resizing
- **📊 Admin Panel** - Manage products, categories, and settings
- **🛍️ Product Management** - Add/edit products with image upload and color variants
- **📄 PDF Generation** - Professional checkout receipts with jsPDF and html2canvas
- **💾 Data Persistence** - JSON-based product database with backup system

## 🚀 Quick Start

### Prerequisites
- Python 3.x (for image conversion script)
- PHP 7.0+ or Apache web server
- Modern web browser

### Installation

**Clone the repository:**
```bash
# Option 1: SSH (requires SSH key setup)
eval "$(ssh-agent -s)"
ssh-add bedroomcult
git clone ssh://git@github.com/bedroomcult/bago-catalog

# Option 2: HTTPS
git clone https://github.com/bedroomcult/bago-catalog.git
```

**Install Python dependencies for image optimization:**
```bash
pip install pillow
```

### Image Optimization (Recommended)

Optimize all product images to WebP format with automatic resizing:
```bash
python convert_to_webp.py
```

This script will:
- Convert all JPG/PNG images to WebP format
- Resize images to maximum 400x400px while preserving aspect ratio
- Update `db.json` to reflect new WebP file paths
- Create backup copies of original images

### Start the Development Server

**Option 1: PHP Built-in Server**
```bash
cd bago-catalog
php -S localhost:8000
```
Then visit: http://localhost:8000

**Option 2: Apache Web Server**
```bash
# Place files in web root and start Apache
sudo httpd
```

## 📁 Project Structure

```
bago-catalog/
├── index.html              # Main catalog page
├── admin.html              # Admin management interface
├── laporan/                # Photo upload/reporting tool
│   └── index.html
├── db.json                 # Product database
├── convert_to_webp.py      # Image optimization script
├── Buffet/                 # Product categories
├── Kursi/
├── Meja/
├── Sofa/
├── Industrial/
├── Set/
├── Divan/
├── Cabinet/
├── Drawer/
├── Nakas/
├── Rotan/
├── Stool/
├── uploaded/               # User uploaded images
└── README.md
```

## 🎨 Font Loading Optimization

The site uses optimized font loading techniques:
- **Preload**: CSS fonts are preloaded for instant availability
- **Swap**: `font-display=swap` prevents invisible text during loading
- **Fallback**: Graceful degradation with noscript support

## 📄 PDF Generation

Professional checkout receipts are generated using:
- **jsPDF**: Serverless PDF creation
- **html2canvas**: High-fidelity canvas rendering
- **Customer data**: Names, phone numbers, timestamps
- **Product details**: Images, names, quantities, discounts

## 🛠️ Development

### Adding New Products

1. **Via Admin Panel**: Use the web interface at `admin.html`
2. **Direct JSON Edit**: Modify `db.json` and add images to appropriate category folders
3. **Bulk Import**: Extend `convert_to_webp.py` for automated importing

### Database Schema

Each product in `db.json` contains:
```json
{
    "name": "Product Name",
    "category": "Category",
    "price": 100000,
    "originalPrice": null,
    "period": "DD/MM/YYYY-DD/MM/YYYY",
    "image": "Category/Product.webp",
    "desc": "Description",
    "size": "Dimensions",
    "colors": [{"hex": "#FFFFFF", "name": "Color Name"}],
    "hidden": false
}
```

## 🚀 Deployment

### Web Server Configuration

**Apache (.htaccess):**
```apache
Options -Indexes
DirectoryIndex index.html
AddType image/webp .webp
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.html;
    index index.html;
}
location ~*\.(webp)$ {
    add_header Cache-Control "public, max-age=31536000";
}
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/new-feature`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/new-feature`)
5. Create a Pull Request

## 📄 License

This project is proprietary software for BAGO Home Decor.

## 🆘 Support

For support and questions, please contact BAGO Home Decor at:
- 📞 Phone: 0811-3202-1021
- 📧 Email: contact@bago.com

---

**Built with ❤️ for BAGO Home Decor**
