<?php

use App\Models\JobCategory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user);
});

test('index displays job categories', function () {
    // Create job categories
    $categories = JobCategory::factory(3)->create();
    
    // Visit the index page
    $response = $this->get(route('job-categories.index'));
    
    // Assert the response is successful
    $response->assertOk();
    
    // Assert the view is correct
    $response->assertViewIs('job-category.index');
    
    // Assert the categories are passed to the view
    $response->assertViewHas('categories');
});

test('index displays archived job categories', function () {
    // Create job categories
    $activeCategories = JobCategory::factory(2)->create();
    $archivedCategories = JobCategory::factory(2)->create();
    
    // Archive some categories
    foreach ($archivedCategories as $category) {
        $category->delete();
    }
    
    // Visit the archived page
    $response = $this->get(route('job-categories.index', ['archived' => 'true']));
    
    // Assert the response is successful
    $response->assertOk();
    
    // Assert the view has the archived categories
    $response->assertViewHas('categories', function ($categories) use ($archivedCategories) {
        return $categories->contains($archivedCategories[0]) && 
               $categories->contains($archivedCategories[1]);
    });
});

test('create page can be rendered', function () {
    $response = $this->get(route('job-categories.create'));
    
    $response->assertOk();
    $response->assertViewIs('job-category.create');
});

test('job category can be stored', function () {
    $categoryData = [
        'name' => 'New Test Category',
    ];
    
    $response = $this->post(route('job-categories.store'), $categoryData);
    
    // Assert the category was created in the database
    $this->assertDatabaseHas('job_categories', $categoryData);
    
    // Assert the user is redirected to the index page
    $response->assertRedirect(route('job-categories.index'));
    
    // Assert a success message was flashed to the session
    $response->assertSessionHas('success', 'Job category created successfully!');
});

test('edit page can be rendered', function () {
    $category = JobCategory::factory()->create();
    
    $response = $this->get(route('job-categories.edit', $category->id));
    
    $response->assertOk();
    $response->assertViewIs('job-category.edit');
    $response->assertViewHas('category', $category);
});

test('job category can be updated', function () {
    $category = JobCategory::factory()->create(['name' => 'Old Name']);
    
    $updatedData = [
        'name' => 'Updated Name',
    ];
    
    $response = $this->put(route('job-categories.update', $category->id), $updatedData);
    
    // Assert the category was updated in the database
    $this->assertDatabaseHas('job_categories', $updatedData);
    
    // Assert the user is redirected to the index page
    $response->assertRedirect(route('job-categories.index'));
    
    // Assert a success message was flashed to the session
    $response->assertSessionHas('success', 'Job category updated successfully!');
});

test('job category can be archived', function () {
    $category = JobCategory::factory()->create();
    
    $response = $this->delete(route('job-categories.destroy', $category->id));
    
    // Assert the category is soft deleted
    $this->assertSoftDeleted('job_categories', ['id' => $category->id]);
    
    // Assert the user is redirected to the index page
    $response->assertRedirect(route('job-categories.index'));
    
    // Assert a success message was flashed to the session
    $response->assertSessionHas('success', 'Job category archived successfully!');
});

test('job category can be restored', function () {
    // Create and delete a category
    $category = JobCategory::factory()->create();
    $category->delete();
    
    // Restore the category
    $response = $this->put(route('job-categories.restore', $category->id));
    
    // Assert the category is restored
    $this->assertDatabaseHas('job_categories', [
        'id' => $category->id,
        'deleted_at' => null,
    ]);
    
    // Assert the user is redirected to the archived index page
    $response->assertRedirect(route('job-categories.index', ['archived' => 'true']));
    
    // Assert a success message was flashed to the session
    $response->assertSessionHas('success', 'Job category restored successfully!');
});

test('validation fails when creating a job category with invalid data', function () {
    // Try to create a category without a name
    $response = $this->post(route('job-categories.store'), [
        'name' => '',
    ]);
    
    // Assert validation fails
    $response->assertSessionHasErrors('name');
});

test('validation fails when updating a job category with invalid data', function () {
    $category = JobCategory::factory()->create();
    
    // Try to update the category without a name
    $response = $this->put(route('job-categories.update', $category->id), [
        'name' => '',
    ]);
    
    // Assert validation fails
    $response->assertSessionHasErrors('name');
});

test('validation fails when creating a job category with duplicate name', function () {
    // Create a category
    $existingCategory = JobCategory::factory()->create(['name' => 'Existing Category']);
    
    // Try to create another category with the same name
    $response = $this->post(route('job-categories.store'), [
        'name' => 'Existing Category',
    ]);
    
    // Assert validation fails
    $response->assertSessionHasErrors('name');
});

test('unauthorized users cannot access job category routes', function () {
    // Create a non-admin user
    $nonAdminUser = User::factory()->create(['role' => 'company-owner']);
    
    // Act as the non-admin user
    $this->actingAs($nonAdminUser);
    
    // Create a category for testing
    $category = JobCategory::factory()->create();
    $deletedCategory = JobCategory::factory()->create();
    $deletedCategory->delete();
    
    // Test all routes
    $this->get(route('job-categories.index'))->assertForbidden();
    $this->get(route('job-categories.create'))->assertForbidden();
    $this->post(route('job-categories.store'), ['name' => 'Test'])->assertForbidden();
    $this->get(route('job-categories.edit', $category->id))->assertForbidden();
    $this->put(route('job-categories.update', $category->id), ['name' => 'Updated'])->assertForbidden();
    $this->delete(route('job-categories.destroy', $category->id))->assertForbidden();
    $this->put(route('job-categories.restore', $deletedCategory->id))->assertForbidden();
});

test('404 is returned when accessing non-existent category', function () {
    // Generate a random UUID that doesn't exist
    $nonExistentId = '12345678-1234-1234-1234-123456789012';
    
    // Test all routes that require an existing category
    $this->get(route('job-categories.edit', $nonExistentId))->assertNotFound();
    $this->put(route('job-categories.update', $nonExistentId), ['name' => 'Test'])->assertNotFound();
    $this->delete(route('job-categories.destroy', $nonExistentId))->assertNotFound();
    $this->put(route('job-categories.restore', $nonExistentId))->assertNotFound();
}); 