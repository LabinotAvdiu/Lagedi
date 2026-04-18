<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the avatar upload/delete endpoints.
 *
 * Covered:
 *   1. Auth user can upload a valid avatar → 200 + profileImageUrl + thumbnailUrl
 *      + users.profile_image_url is persisted + 2 files exist on disk
 *   2. Upload rejects a non-image file → 422
 *   3. Upload rejects a file larger than 8 MB → 422
 *   4. Re-upload replaces the old files (no stale files left)
 *   5. Auth user can delete their avatar → 204 + column nulled + files deleted
 *   6. Anonymous requests return 401 on both POST and DELETE
 */
class UserAvatarTest extends TestCase
{
    // =========================================================================
    // 1. Successful upload
    // =========================================================================

    public function test_authenticated_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['profile_image_url' => null]);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 800, 800);

        $response = $this->postJson('/api/me/avatar', ['photo' => $file]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['profileImageUrl', 'thumbnailUrl'])
                 ->assertJsonPath('profileImageUrl', fn ($v) => is_string($v) && strlen($v) > 0)
                 ->assertJsonPath('thumbnailUrl', fn ($v) => is_string($v) && strlen($v) > 0);

        // Colonne mise à jour
        $this->assertNotNull($user->fresh()->profile_image_url);

        // Les deux fichiers existent
        $allFiles = Storage::disk('public')->allFiles("avatars/{$user->id}");
        $this->assertCount(2, $allFiles);

        $mediumFiles = array_filter($allFiles, fn ($f) => str_contains($f, '/medium/'));
        $thumbFiles  = array_filter($allFiles, fn ($f) => str_contains($f, '/thumb/'));
        $this->assertCount(1, $mediumFiles);
        $this->assertCount(1, $thumbFiles);
    }

    // =========================================================================
    // 2. Reject non-image file
    // =========================================================================

    public function test_upload_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->postJson('/api/me/avatar', ['photo' => $file]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['photo']);
    }

    // =========================================================================
    // 3. Reject file > 8 MB
    // =========================================================================

    public function test_upload_rejects_file_over_8mb(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 9 000 KB ~ 8.79 MB — above the 8 192 KB (8 MB) limit
        $file = UploadedFile::fake()->create('big.jpg', 9000, 'image/jpeg');

        $response = $this->postJson('/api/me/avatar', ['photo' => $file]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['photo']);
    }

    // =========================================================================
    // 4. Re-upload replaces the old files
    // =========================================================================

    public function test_reupload_deletes_previous_avatar_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = UploadedFile::fake()->image('first.jpg', 400, 400);
        $this->postJson('/api/me/avatar', ['photo' => $first])->assertStatus(200);

        $countAfterFirst = count(Storage::disk('public')->allFiles("avatars/{$user->id}"));
        $this->assertSame(2, $countAfterFirst, 'First upload should create exactly 2 files.');

        $second = UploadedFile::fake()->image('second.jpg', 600, 600);
        $this->postJson('/api/me/avatar', ['photo' => $second])->assertStatus(200);

        $filesAfterSecond = Storage::disk('public')->allFiles("avatars/{$user->id}");
        $this->assertCount(2, $filesAfterSecond, 'Re-upload must leave exactly 2 files (old ones deleted).');
    }

    // =========================================================================
    // 5. Successful delete
    // =========================================================================

    public function test_authenticated_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Upload first so there is something to delete
        $file = UploadedFile::fake()->image('del.jpg', 300, 300);
        $this->postJson('/api/me/avatar', ['photo' => $file])->assertStatus(200);

        $this->assertNotNull($user->fresh()->profile_image_url);
        $this->assertNotEmpty(Storage::disk('public')->allFiles("avatars/{$user->id}"));

        $response = $this->deleteJson('/api/me/avatar');

        $response->assertStatus(204);

        $this->assertNull($user->fresh()->profile_image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles("avatars/{$user->id}"));
    }

    // =========================================================================
    // 6. Unauthenticated requests return 401
    // =========================================================================

    public function test_anonymous_cannot_upload_or_delete_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('anon.jpg', 200, 200);

        $this->postJson('/api/me/avatar', ['photo' => $file])->assertStatus(401);
        $this->deleteJson('/api/me/avatar')->assertStatus(401);
    }
}
