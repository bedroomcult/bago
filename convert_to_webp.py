#!/usr/bin/env python3
"""
Image Converter to WebP
Converts all non-WebP/SVG images to WebP format with 400x400px max resize
and updates db.json to reflect new file paths.

Requirements:
- pip install pillow

Usage:
python convert_to_webp.py
"""

import os
import json
import shutil
from pathlib import Path
from PIL import Image
import sys

# Supported image formats to convert (exclude webp and svg)
SUPPORTED_FORMATS = {'.jpg', '.jpeg', '.png', '.bmp', '.gif', '.tiff', '.ico'}

def check_dependencies():
    """Check if required dependencies are installed."""
    try:
        import PIL
        print("✅ PIL/Pillow is installed")
        return True
    except ImportError:
        print("❌ PIL/Pillow is not installed. Please run: pip install pillow")
        return False

def get_image_files(root_dir):
    """Scan all subdirectories for convertible image files."""
    image_files = []
    root_path = Path(root_dir)

    print("🔍 Scanning for convertible images...")

    # Scan furniture category directories
    categories = [
        'Buffet', 'Cabinet', 'Divan', 'Drawer', 'Industrial', 'Kursi',
        'Meja', 'Nakas', 'Rotan', 'Set', 'Sofa', 'Stool', 'uploaded'
    ]

    for category in categories:
        category_path = root_path / category
        if category_path.exists() and category_path.is_dir():
            print(f"📁 Scanning {category}...")
            for file_path in category_path.rglob('*'):
                if file_path.is_file() and file_path.suffix.lower() in SUPPORTED_FORMATS:
                    image_files.append(file_path)

    return image_files

def convert_and_resize_image(input_path, output_path, max_size=(400, 400)):
    """Convert image to WebP format and resize to max dimensions."""
    try:
        with Image.open(input_path) as img:
            # Handle EXIF orientation
            try:
                from PIL import ImageOps
                img = ImageOps.exif_transpose(img)
            except Exception:
                pass  # Continue without EXIF handling if it fails

            # Convert to RGB if necessary (for PNG with transparency, etc.)
            if img.mode in ('RGBA', 'LA', 'P'):
                # Create white background for transparency
                background = Image.new('RGB', img.size, (255, 255, 255))
                if img.mode == 'P':
                    img = img.convert('RGBA')
                background.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
                img = background
            elif img.mode != 'RGB':
                img = img.convert('RGB')

            # Resize while maintaining aspect ratio
            img.thumbnail(max_size, Image.Resampling.LANCZOS)

            # Save as WebP with quality optimization
            img.save(output_path, 'WebP', quality=85, optimize=True)

            return True, f"✅ Converted {input_path.name} ({img.size[0]}×{img.size[1]})"

    except Exception as e:
        return False, f"❌ Failed to convert {input_path.name}: {str(e)}"

def backup_original_file(file_path):
    """Create backup of original file."""
    backup_path = file_path.with_suffix(file_path.suffix + '.backup')
    try:
        shutil.copy2(file_path, backup_path)
        return True
    except Exception as e:
        print(f"⚠️  Failed to backup {file_path.name}: {e}")
        return False

def update_db_json(db_path, converted_files):
    """Update db.json to point to .webp files instead of original formats."""
    try:
        with open(db_path, 'r', encoding='utf-8') as f:
            data = json.load(f)

        updated_count = 0

        for item in data:
            if 'image' in item:
                image_path = item['image']
                # Convert path extensions
                for old_ext in SUPPORTED_FORMATS:
                    if image_path.lower().endswith(old_ext):
                        new_path = image_path.rsplit('.', 1)[0] + '.webp'
                        item['image'] = new_path
                        updated_count += 1
                        break

        # Save updated database
        with open(db_path, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=4, ensure_ascii=False)

        return updated_count

    except Exception as e:
        print(f"❌ Failed to update db.json: {e}")
        return 0

def main():
    """Main conversion function."""
    print("🖼️  Image to WebP Converter with db.json Update")
    print("=" * 50)

    # Check dependencies
    if not check_dependencies():
        return

    # Get script directory
    script_dir = Path(__file__).parent.absolute()

    # Find all convertible images
    image_files = get_image_files(script_dir)

    if not image_files:
        print("ℹ️  No convertible images found.")
        return

    print(f"\n📊 Found {len(image_files)} images to convert:")
    for img in image_files:
        print(f"  • {img.relative_to(script_dir)}")

    # Ask for confirmation
    response = input(f"\n🔄 Convert {len(image_files)} images to WebP format? (y/N): ").strip().lower()
    if response not in ['y', 'yes']:
        print("❌ Conversion cancelled.")
        return

    # Conversion process
    print("\n🚀 Starting conversion process...")
    converted = 0
    skipped = 0
    errors = 0

    for i, input_path in enumerate(image_files, 1):
        # Create output path (change extension to .webp)
        output_path = input_path.with_suffix('.webp')

        # Skip if WebP already exists
        if output_path.exists():
            print(f"[{i}/{len(image_files)}] ⏭️  Skipping {input_path.name} (WebP already exists)")
            skipped += 1
            continue

        print(f"[{i}/{len(image_files)}] 🔄 Converting {input_path.name}...")

        # Backup original
        if not backup_original_file(input_path):
            print(f"      ⚠️  Skipping {input_path.name} due to backup failure")
            errors += 1
            continue

        # Convert image
        success, message = convert_and_resize_image(input_path, output_path)
        print(f"         {message}")

        if success:
            converted += 1

            # Remove original file after successful conversion
            try:
                input_path.unlink()
                print(f"         🗑️  Removed original {input_path.name}")
            except Exception as e:
                print(f"         ⚠️  Could not remove original: {e}")
        else:
            # Restore from backup if conversion failed
            backup_path = input_path.with_suffix(input_path.suffix + '.backup')
            if backup_path.exists():
                backup_path.replace(input_path)
            errors += 1

    # Update database
    print("\n📝 Updating db.json...")
    db_path = script_dir / 'db.json'
    if db_path.exists():
        updated_paths = update_db_json(db_path, image_files)
        print(f"📊 Updated {updated_paths} image paths in db.json")
    else:
        print("⚠️  db.json not found, skipping database update")

    # Final summary
    print("\n🎉 Conversion complete!")
    print(f"✅ Successfully converted: {converted} images")
    print(f"⏭️  Skipped (already exist): {skipped} images")
    print(f"❌ Errors: {errors} images")

    if converted > 0:
        print(f"\n📏 All converted images resized to max 400×400px while maintaining aspect ratio")
        print(f"💾 Original files backed up with '.backup' extension")
        print(f"🗂️  Database paths updated to point to .webp files")

if __name__ == "__main__":
    main()
