<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryDirectoryRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_categories_index_redirects_to_services_index(): void
    {
        $this->get('/categories')
            ->assertStatus(301)
            ->assertRedirect(route('services.index'));
    }

    public function test_legacy_category_and_subcategory_pages_redirect_to_services_directory_routes(): void
    {
        $category = Category::query()->create([
            'name' => 'Calibration & Testing Services',
            'slug' => 'calibration-testing-services',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Pressure Gauge',
            'slug' => 'pressure-gauge',
            'is_active' => true,
        ]);

        $this->get("/categories/{$category->slug}")
            ->assertStatus(301)
            ->assertRedirect(route('services.category', ['category' => $category->slug]));

        $this->get("/categories/{$category->slug}/{$subcategory->slug}")
            ->assertStatus(301)
            ->assertRedirect(route('services.subcategory', [
                'category' => $category->slug,
                'subcategory' => $subcategory->slug,
            ]));
    }

    public function test_sitemap_omits_removed_category_pages_and_keeps_service_directory_urls(): void
    {
        $category = Category::query()->create([
            'name' => 'Calibration & Testing Services',
            'slug' => 'calibration-testing-services',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Pressure Gauge',
            'slug' => 'pressure-gauge',
            'is_active' => true,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString(route('services.category', ['category' => $category->slug]), $content);
        $this->assertStringContainsString(route('services.subcategory', [
            'category' => $category->slug,
            'subcategory' => $subcategory->slug,
        ]), $content);
        $this->assertStringNotContainsString(url('/categories'), $content);
        $this->assertStringNotContainsString(route('categories.show', ['category' => $category->slug]), $content);
        $this->assertStringNotContainsString(route('subcategories.show', [
            'category' => $category->slug,
            'subcategory' => $subcategory->slug,
        ]), $content);
    }
}
