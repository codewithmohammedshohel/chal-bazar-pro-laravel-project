<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Demo catalog only. Prices are illustrative and are NOT live market rates.
 * The seeder is idempotent: running it repeatedly updates the same records
 * instead of duplicating them.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $rice = $this->category([
            'slug' => 'rice',
            'name_bn' => 'চাল',
            'name_en' => 'Rice',
            'subtitle_bn' => 'দৈনন্দিন ও পরিবারিক প্যাক',
            'subtitle_en' => 'Everyday and family packs',
            'sort_order' => 1,
        ]);

        $aromatic = $this->category([
            'slug' => 'aromatic-rice',
            'name_bn' => 'সুগন্ধি চাল',
            'name_en' => 'Aromatic Rice',
            'subtitle_bn' => 'বাসমতী, চিনিগুঁড়া ও পোলাও',
            'subtitle_en' => 'Basmati, chinigura and polao',
            'sort_order' => 2,
        ]);

        $grocery = $this->category([
            'slug' => 'grocery',
            'name_bn' => 'নিত্যপণ্য',
            'name_en' => 'Grocery & Essentials',
            'subtitle_bn' => 'তেল, ডাল ও চিনি',
            'subtitle_en' => 'Oil, lentils and sugar',
            'sort_order' => 3,
        ]);

        $spices = $this->category([
            'slug' => 'spices',
            'name_bn' => 'মসলা',
            'name_en' => 'Spices',
            'subtitle_bn' => 'গুঁড়া মসলা',
            'subtitle_en' => 'Ground spices',
            'sort_order' => 4,
        ]);

        $this->product([
            'category_id' => $rice->id,
            'type' => Product::TYPE_RICE,
            'slug' => 'miniket-rice',
            'title' => 'Miniket Rice',
            'name_bn' => 'মিনিকেট চাল',
            'name_en' => 'Miniket Rice',
            'description' => 'ডেমো ক্যাটালগের মিনিকেট চাল। ঝরঝরে ভাতের জন্য জনপ্রিয়। দাম শুধু ডেমো উদ্দেশ্যে দেওয়া হয়েছে।',
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Miniket',
            'processing_method' => 'Auto sortex',
            'is_featured' => true,
            'meta_title' => 'মিনিকেট চাল | Chal Bazar Pro',
            'meta_description' => 'মিনিকেট চালের ডেমো ক্যাটালগ — কেজি ও বস্তা প্যাক।',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 72.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 1750.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
            ['unit' => ProductVariant::UNIT_SACK_50KG, 'weight_kg' => 50, 'retail_price' => 3450.00, 'label_bn' => '৫০ কেজি বস্তা', 'label_en' => '50 kg sack', 'sort_order' => 2],
        ], [
            ['url' => '/images/demo/rice-1.svg', 'alt_bn' => 'মিনিকেট চালের ডেমো ছবি', 'alt_en' => 'Demo miniket rice image'],
        ]);

        $this->product([
            'category_id' => $aromatic->id,
            'type' => Product::TYPE_RICE,
            'slug' => 'basmati-rice',
            'title' => 'Basmati Rice',
            'name_bn' => 'বাসমতী চাল',
            'name_en' => 'Basmati Rice',
            'description' => 'ডেমো ক্যাটালগের বাসমতী চাল। সুগন্ধি ও লম্বা দানার জন্য পরিচিত। দাম ডেমো।',
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Basmati',
            'processing_method' => 'Aged and sortexed',
            'is_featured' => true,
            'meta_title' => 'বাসমতী চাল | Chal Bazar Pro',
            'meta_description' => 'বাসমতী চালের ডেমো ক্যাটালগ — কেজি ও বস্তা প্যাক।',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 145.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 3550.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
            ['unit' => ProductVariant::UNIT_SACK_50KG, 'weight_kg' => 50, 'retail_price' => 6950.00, 'label_bn' => '৫০ কেজি বস্তা', 'label_en' => '50 kg sack', 'sort_order' => 2],
        ], [
            ['url' => '/images/demo/rice-2.svg', 'alt_bn' => 'বাসমতী চালের ডেমো ছবি', 'alt_en' => 'Demo basmati rice image'],
        ]);

        $this->product([
            'category_id' => $aromatic->id,
            'type' => Product::TYPE_RICE,
            'slug' => 'chinigura-rice',
            'title' => 'Chinigura Rice',
            'name_bn' => 'চিনিগুঁড়া চাল',
            'name_en' => 'Chinigura Rice',
            'description' => 'ডেমো ক্যাটালগের চিনিগুঁড়া চাল। পোলাও ও বিশেষ রান্নার জন্য। দাম ডেমো।',
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Chinigura',
            'processing_method' => 'Aged',
            'is_featured' => true,
            'meta_title' => 'চিনিগুঁড়া চাল | Chal Bazar Pro',
            'meta_description' => 'চিনিগুঁড়া চালের ডেমো ক্যাটালগ।',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 155.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 3800.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/rice-3.svg', 'alt_bn' => 'চিনিগুঁড়া চালের ডেমো ছবি', 'alt_en' => 'Demo chinigura rice image'],
        ]);

        $this->product([
            'category_id' => $rice->id,
            'type' => Product::TYPE_RICE,
            'slug' => 'polao-rice',
            'title' => 'Polao Rice',
            'name_bn' => 'পোলাও চাল',
            'name_en' => 'Polao Rice',
            'description' => 'ডেমো ক্যাটালগের পোলাও চাল। দাম ডেমো।',
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Polao',
            'processing_method' => 'Sortex',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 110.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 2680.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/rice-1.svg', 'alt_bn' => 'পোলাও চালের ডেমো ছবি', 'alt_en' => 'Demo polao rice image'],
        ]);

        $this->product([
            'category_id' => $rice->id,
            'type' => Product::TYPE_RICE,
            'slug' => 'brown-rice',
            'title' => 'Brown Rice',
            'name_bn' => 'ব্রাউন রাইস',
            'name_en' => 'Brown Rice',
            'description' => 'ডেমো ক্যাটালগের ব্রাউন রাইস। দাম ডেমো।',
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Brown',
            'processing_method' => 'Hulled',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 95.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 2320.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/rice-2.svg', 'alt_bn' => 'ব্রাউন রাইসের ডেমো ছবি', 'alt_en' => 'Demo brown rice image'],
        ]);

        $this->product([
            'category_id' => $rice->id,
            'type' => Product::TYPE_RICE,
            'slug' => 'atap-rice',
            'title' => 'Atap Rice',
            'name_bn' => 'আতপ চাল',
            'name_en' => 'Atap Rice',
            'description' => 'ডেমো ক্যাটালগের আতপ চাল। দাম ডেমো।',
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Atap',
            'processing_method' => 'Parboiled',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 68.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 1650.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/rice-3.svg', 'alt_bn' => 'আতপ চালের ডেমো ছবি', 'alt_en' => 'Demo atap rice image'],
        ]);

        $this->product([
            'category_id' => $grocery->id,
            'type' => Product::TYPE_OTHER,
            'slug' => 'soybean-oil',
            'title' => 'Soybean Oil',
            'name_bn' => 'সয়াবিন তেল',
            'name_en' => 'Soybean Oil',
            'description' => 'ডেমো ক্যাটালগের সয়াবিন তেল। দাম ডেমো।',
            'is_featured' => false,
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 168.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 4100.00, 'label_bn' => '২৫ কেজি', 'label_en' => '25 kg', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/grocery-1.svg', 'alt_bn' => 'সয়াবিন তেলের ডেমো ছবি', 'alt_en' => 'Demo soybean oil image'],
        ]);

        $this->product([
            'category_id' => $grocery->id,
            'type' => Product::TYPE_OTHER,
            'slug' => 'masoor-dal',
            'title' => 'Red Lentil (Masoor Dal)',
            'name_bn' => 'মসুর ডাল',
            'name_en' => 'Red Lentil (Masoor Dal)',
            'description' => 'ডেমো ক্যাটালগের মসুর ডাল। দাম ডেমো।',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 135.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 3250.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/grocery-2.svg', 'alt_bn' => 'মসুর ডালের ডেমো ছবি', 'alt_en' => 'Demo red lentil image'],
        ]);

        $this->product([
            'category_id' => $grocery->id,
            'type' => Product::TYPE_OTHER,
            'slug' => 'sugar',
            'title' => 'Sugar',
            'name_bn' => 'চিনি',
            'name_en' => 'Sugar',
            'description' => 'ডেমো ক্যাটালগের চিনি। দাম ডেমো।',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 125.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
            ['unit' => ProductVariant::UNIT_SACK_25KG, 'weight_kg' => 25, 'retail_price' => 3050.00, 'label_bn' => '২৫ কেজি বস্তা', 'label_en' => '25 kg sack', 'sort_order' => 1],
        ], [
            ['url' => '/images/demo/grocery-1.svg', 'alt_bn' => 'চিনির ডেমো ছবি', 'alt_en' => 'Demo sugar image'],
        ]);

        $this->product([
            'category_id' => $spices->id,
            'type' => Product::TYPE_OTHER,
            'slug' => 'chili-powder',
            'title' => 'Chili Powder',
            'name_bn' => 'মরিচ গুঁড়া',
            'name_en' => 'Chili Powder',
            'description' => 'ডেমো ক্যাটালগের মরিচ গুঁড়া। দাম ডেমো।',
        ], [
            ['unit' => ProductVariant::UNIT_KG, 'weight_kg' => 1, 'retail_price' => 320.00, 'label_bn' => '১ কেজি', 'label_en' => '1 kg', 'sort_order' => 0],
        ], [
            ['url' => '/images/demo/spice-1.svg', 'alt_bn' => 'মরিচ গুঁড়ার ডেমো ছবি', 'alt_en' => 'Demo chili powder image'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function category(array $attributes): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes + ['is_active' => true],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $variants
     * @param  list<array<string, mixed>>  $media
     */
    private function product(array $attributes, array $variants, array $media): void
    {
        $product = Product::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes + ['is_active' => true],
        );

        foreach ($variants as $variant) {
            $product->variants()->updateOrCreate(
                ['unit' => $variant['unit']],
                $variant + ['is_active' => true],
            );
        }

        foreach ($media as $index => $item) {
            $product->media()->updateOrCreate(
                ['url' => $item['url']],
                $item + ['kind' => 'image', 'sort_order' => $index],
            );
        }
    }
}
