<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // (posts.index) Test that the index returns only active posts.
    public function test_index_returns_active_posts(): void
    {
        // Send GET request to /posts
        $response = $this->get('/posts');

        // Response should be OK
        $response->assertStatus(200);

        // Response should contain pagination data
        $response->assertJsonStructure([
            'current_page',
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'title',
                    'content',
                    'is_draft',
                    'published_at',
                    'created_at',
                    'updated_at',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ],
        ]);

        // Maximum 20 posts per page
        $this->assertLessThanOrEqual(
            20,
            count($response->json('data'))
        );
    }

    // (posts.index)Test that draft and scheduled posts are excluded from the index.
    public function test_index_excludes_draft_and_scheduled_posts(): void
    {
        $user = User::first();

        // Active post
        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Active Post',
            'is_draft' => false,
            'published_at' => now()->subMinute(),
        ]);

        // Draft
        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'This is Draft Post',
            'is_draft' => true,
            'published_at' => now()->subMinute(),
        ]);

        // Scheduled
        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'This is Scheduled Post',
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get('/posts');

        $response->assertStatus(200);

        // Draft must not appear
        $response->assertJsonMissing([
            'title' => 'This is Draft Post',
        ]);

        // Scheduled must not appear
        $response->assertJsonMissing([
            'title' => 'This is Scheduled Post',
        ]);
    }

    // (posts.create) Test that an authenticated user can access the create page.
    public function test_authenticated_user_can_access_create(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Simulate a logged-in user and send a GET request
        $response = $this->actingAs($user)
            ->get('/posts/create');

        // Verify that the request returns HTTP 200 (OK)
        $response->assertStatus(200);

        // Verify that the create page identifier is returned
        $response->assertSee('posts.create');
    }

    // posts.create Test that guests cannot access the create page.
    public function test_guest_cannot_access_create(): void
    {
        // Send a GET request without authentication
        $response = $this->get('/posts/create');

        // Verify that guests are redirected to the login page
        $response->assertRedirect('/login');
    }

    // (posts.store) Test that an authenticated user can create a new post.
    public function test_authenticated_user_can_create_post(): void
    {

        // Get the first user from the seeded database
        $user = User::first();

        // Simulate a logged-in user and send a POST request
        $response = $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Laravel Skill Test',
                'content' => 'Learn Feature Test',
                'is_draft' => false,
                'published_at' => now(),
            ]);

        // Verify that the request returns HTTP 201 (Created)
        $response->assertStatus(201);

        // Verify that the new post exists in the database
        $this->assertDatabaseHas('posts', [
            'title' => 'Laravel Skill Test',
        ]);
    }

    // (posts.show) Test that an active post can be viewed.
    public function test_can_view_active_post(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Create an active post
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subMinute(),
        ]);

        // Send a GET request
        $response = $this->get("/posts/{$post->id}");

        // Verify that the request returns HTTP 200 (OK)
        $response->assertOk();

        // Verify that the post is returned
        $response->assertJsonFragment([
            'id' => $post->id,
            'title' => $post->title,
        ]);
    }

    // (posts.show) Test that a draft post returns 404.
    public function test_draft_post_returns_404(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Create a draft post
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => null,
        ]);

        // Send a GET request
        $response = $this->get("/posts/{$post->id}");

        // Verify that the request returns HTTP 404 (Not Found)
        $response->assertNotFound();
    }

    // (posts.show) Test that a scheduled post returns 404.
    public function test_scheduled_post_returns_404(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Create a scheduled post
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        // Send a GET request
        $response = $this->get("/posts/{$post->id}");

        // Verify that the request returns HTTP 404 (Not Found)
        $response->assertNotFound();
    }

    // (posts.edit) Test that the post author can access the edit page.
    public function test_post_author_can_access_edit(): void
    {
        // Get existing user
        $user = User::first();

        // Create post owned by that user
        $post = Post::factory()->create([
            'user_id' => $user->id,
        ]);

        // Login as owner
        $response = $this->actingAs($user)
            ->get("/posts/{$post->id}/edit");

        // Verify that the request returns HTTP 200 (OK)
        $response->assertStatus(200);

        // Should return string "posts.edit"
        $response->assertSee('posts.edit');
    }

    // (posts.edit) Test that a non-author cannot access the edit page.
    public function test_non_author_cannot_access_edit(): void
    {
        // Post owner
        $owner = User::factory()->create();

        // Another user
        $anotherUser = User::factory()->create();

        // Create post
        $post = Post::factory()->create([
            'user_id' => $owner->id,
        ]);

        // Login as another user
        $response = $this->actingAs($anotherUser)
            ->get("/posts/{$post->id}/edit");

        // Should be forbidden
        $response->assertForbidden();
    }

    // (posts.update) Test that the post author can update the post.
    public function test_post_author_can_update_post(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Create a post owned by the user
        $post = Post::factory()->create([
            'user_id' => $user->id,
        ]);

        // Login as the post owner and send a PUT request
        $response = $this->actingAs($user)
            ->put("/posts/{$post->id}", [
                'title' => 'Updated Title',
                'content' => 'Updated Content',
                'is_draft' => false,
                'published_at' => now(),
            ]);

        // Verify that the request returns HTTP 200 (OK)
        $response->assertStatus(200);

        // Verify that the database has been updated
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'content' => 'Updated Content',
        ]);
    }

    // (posts.update) Test that a non-author cannot update the post.
    public function test_non_author_cannot_update_post(): void
    {
        // Create the post owner
        $owner = User::factory()->create();

        // Create another user
        $anotherUser = User::factory()->create();

        // Create a post
        $post = Post::factory()->create([
            'user_id' => $owner->id,
        ]);

        // Login as another user and try to update the post
        $response = $this->actingAs($anotherUser)
            ->put("/posts/{$post->id}", [
                'title' => 'Hacked Title',
                'content' => 'Hacked Content',
            ]);

        // Verify that access is forbidden
        $response->assertForbidden();
    }

    // (posts.update) Test that validation is applied when updating a post.
    public function test_update_requires_valid_data(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Create a post
        $post = Post::factory()->create([
            'user_id' => $user->id,
        ]);

        // Login and submit invalid data
        $response = $this->actingAs($user)
            ->put("/posts/{$post->id}", [
                'title' => '',
                'content' => '',
            ]);

        // Verify validation errors exist
        $response->assertSessionHasErrors([
            'title',
            'content',
        ]);
    }

    // (posts.destroy) Test that the post author can delete the post.
    public function test_post_author_can_delete_post(): void
    {
        // Get the first user from the seeded database
        $user = User::first();

        // Create a post owned by the user
        $post = Post::factory()->create([
            'user_id' => $user->id,
        ]);

        // Simulate a logged-in user and send a DELETE request
        $response = $this->actingAs($user)
            ->delete("/posts/{$post->id}");

        // Verify that the request returns HTTP 204 (No Content)
        $response->assertNoContent();

        // Verify that the post no longer exists
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    // (posts.destroy) Test that a non-author cannot delete the post.
    public function test_non_author_cannot_delete_post(): void
    {
        // Create the post owner
        $owner = User::factory()->create();

        // Create another user
        $anotherUser = User::factory()->create();

        // Create a post owned by the owner
        $post = Post::factory()->create([
            'user_id' => $owner->id,
        ]);

        // Simulate another logged-in user
        $response = $this->actingAs($anotherUser)
            ->delete("/posts/{$post->id}");

        // Verify that access is forbidden
        $response->assertForbidden();

        // Verify that the post still exists
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }
}
