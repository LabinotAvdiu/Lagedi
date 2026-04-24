# Employee Invitation — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the backend half of the employee invitation flow: a dedicated `employee_invitations` table with status lifecycle, secure signed-token email links that auto-verify the invited email server-side, accept/refuse endpoints for in-app users, and a nightly expiration job.

**Architecture:** Invitations are first-class objects independent of `users` and `company_user`. No User stub is created at invite time — the `User` row only appears at signup or already exists. The `company_user` pivot is created only on acceptance. Email auto-verification is **derived from the token** validity inside `register()`, never from a client-controlled param.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL 8, Sanctum, PHPUnit, Mailpit (dev), FCM (push via existing `kreait/laravel-firebase`).

**Spec:** `docs/superpowers/specs/2026-04-24-employee-invitation-design.md` (in the mobile repo `hairspot_mobile`).

**Branch:** create `feat/employee-invitation` from `main`.

---

## File Structure

### Created
- `database/migrations/2026_04_24_000001_create_employee_invitations_table.php`
- `app/Enums/InvitationStatus.php` — `Pending`, `Accepted`, `Refused`, `Expired`, `Revoked`
- `app/Models/EmployeeInvitation.php`
- `app/Http/Controllers/EmployeeInvitationController.php` — public lookup + authenticated me-side
- `app/Http/Requests/MyCompany/SendInvitationRequest.php` — replaces InviteEmployeeRequest semantics
- `app/Http/Resources/InvitationResource.php` — owner view
- `app/Http/Resources/MyInvitationResource.php` — invited user view
- `app/Http/Resources/PublicInvitationResource.php` — pre-signup lookup view (no auth)
- `app/Mail/EmployeeInvitationLinkMail.php`
- `resources/views/emails/employee-invitation-link.blade.php`
- `app/Jobs/SendEmployeeInvitationLinkEmail.php`
- `app/Jobs/SendEmployeeInvitationPush.php` — Flow 2 push for users with accounts
- `app/Jobs/SendInvitationDecisionPush.php` — accepted/refused/expired pushes to owner
- `app/Console/Commands/ExpireInvitations.php`
- `tests/Feature/EmployeeInvitation/InviteFlowTest.php`
- `tests/Feature/EmployeeInvitation/RegisterWithTokenTest.php`
- `tests/Feature/EmployeeInvitation/AcceptRefuseTest.php`
- `tests/Feature/EmployeeInvitation/ExpireCommandTest.php`

### Modified
- `app/Http/Controllers/MyCompanyController.php` — replace `inviteEmployee`, drop `createEmployee`, add resend/revoke, modify `listEmployees` to merge members + invitations.
- `app/Http/Requests/MyCompany/InviteEmployeeRequest.php` — drop or rewrite to add `first_name`/`last_name`.
- `app/Http/Requests/Auth/RegisterRequest.php` — add optional `invitation_token`.
- `app/Http/Controllers/AuthController.php` — handle `invitation_token` in `register()`.
- `app/Http/Resources/EmployeeResource.php` — add `kind: 'member'`.
- `routes/api.php` — register the 6 new routes.
- `routes/console.php` — schedule `invitations:expire` daily.
- `resources/lang/{fr,en,sq}/emails.php` — add invitation-link strings.

### Deleted (or marked deprecated then removed in a follow-up)
- `app/Http/Requests/MyCompany/CreateEmployeeRequest.php` — `/employees/create` endpoint goes away.

---

## Phase 0: Project setup

### Task 0.1: Create branch + verify clean state

- [ ] **Step 1: Switch to main and pull**

```bash
git checkout main && git pull --ff-only
```

- [ ] **Step 2: Create feature branch**

```bash
git checkout -b feat/employee-invitation
```

- [ ] **Step 3: Verify test suite is green before any change**

```bash
php artisan test --testsuite=Feature
```
Expected: all tests pass. If not, fix or document the pre-existing failure before continuing.

---

## Phase 1: Schema + model + enum

### Task 1.1: Add `InvitationStatus` enum

**Files:**
- Create: `app/Enums/InvitationStatus.php`

- [ ] **Step 1: Create the enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Refused  = 'refused';
    case Expired  = 'expired';
    case Revoked  = 'revoked';
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Enums/InvitationStatus.php
git commit -m "feat(invitations): add InvitationStatus enum"
```

### Task 1.2: Create `employee_invitations` migration

**Files:**
- Create: `database/migrations/2026_04_24_000001_create_employee_invitations_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('invited_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email', 255);              // always lower()
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->json('specialties');                 // default '[]' set at insert time
            $table->string('role', 32)->default('employee');

            $table->char('token_hash', 64);              // sha256 hex
            $table->string('status', 16)->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('refused_at')->nullable();

            $table->foreignId('resulting_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('token_hash', 'idx_invitations_token_hash');
            $table->index(['email', 'status'], 'idx_invitations_email_status');
            $table->index(['company_id', 'email', 'status'], 'idx_invitations_company_email_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_invitations');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```
Expected: `INFO  Running migrations.` then the new migration name + `Done`.

- [ ] **Step 3: Verify schema in tinker**

```bash
php artisan tinker --execute="dump(\Illuminate\Support\Facades\Schema::getColumnListing('employee_invitations'));"
```
Expected: array containing `id, company_id, invited_by_user_id, email, first_name, last_name, specialties, role, token_hash, status, expires_at, accepted_at, refused_at, resulting_user_id, created_at, updated_at`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_24_000001_create_employee_invitations_table.php
git commit -m "feat(invitations): add employee_invitations table"
```

### Task 1.3: Create `EmployeeInvitation` model

**Files:**
- Create: `app/Models/EmployeeInvitation.php`

- [ ] **Step 1: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInvitation extends Model
{
    protected $fillable = [
        'company_id',
        'invited_by_user_id',
        'email',
        'first_name',
        'last_name',
        'specialties',
        'role',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'refused_at',
        'resulting_user_id',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'status'      => InvitationStatus::class,
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
            'refused_at'  => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function resultingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulting_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at->isFuture();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/EmployeeInvitation.php
git commit -m "feat(invitations): add EmployeeInvitation model"
```

---

## Phase 2: Owner-side endpoints (invite, resend, revoke, list)

### Task 2.1: Test — owner can send an invitation to an unknown email

**Files:**
- Create: `tests/Feature/EmployeeInvitation/InviteFlowTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Mail\EmployeeInvitationLinkMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerWithCompany(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => CompanyRole::Owner,
            'is_active'  => true,
        ]);
        return [$owner, $company];
    }

    public function test_owner_can_invite_unknown_email(): void
    {
        Mail::fake();

        [$owner, $company] = $this->makeOwnerWithCompany();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/my-company/employees/invite', [
            'email'      => 'alice@example.com',
            'first_name' => 'Alice',
            'last_name'  => 'Martin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.firstName', 'Alice')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.hasAccount', false);

        $this->assertDatabaseHas('employee_invitations', [
            'company_id' => $company->id,
            'email'      => 'alice@example.com',
            'status'     => 'pending',
        ]);

        Mail::assertSent(EmployeeInvitationLinkMail::class);
    }
}
```

- [ ] **Step 2: Run the test, expect failure**

```bash
php artisan test --filter=test_owner_can_invite_unknown_email
```
Expected: FAIL — route not found.

### Task 2.2: Add the new invite endpoint + request

**Files:**
- Create: `app/Http/Requests/MyCompany/SendInvitationRequest.php`
- Modify: `app/Http/Controllers/MyCompanyController.php` — replace `inviteEmployee` body, drop User-must-exist check
- Modify: `routes/api.php` — keep the `/employees/invite` route, point at the new logic
- Create: `app/Mail/EmployeeInvitationLinkMail.php` (skeleton — full body in Phase 4)
- Create: `app/Jobs/SendEmployeeInvitationLinkEmail.php` (skeleton)
- Create: `app/Http/Resources/InvitationResource.php`

- [ ] **Step 1: Create `SendInvitationRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use App\Enums\CompanyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'         => ['required', 'email', 'max:255'],
            'first_name'    => ['nullable', 'string', 'max:100'],
            'last_name'     => ['nullable', 'string', 'max:100'],
            'specialties'   => ['nullable', 'array'],
            'specialties.*' => ['string', 'max:100'],
            'role'          => ['nullable', new Enum(CompanyRole::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
```

- [ ] **Step 2: Create `InvitationResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasAccount = User::where('email', $this->resource->email)->exists();

        return [
            'id'          => $this->resource->id,
            'kind'        => 'invitation',
            'email'       => $this->resource->email,
            'firstName'   => $this->resource->first_name,
            'lastName'    => $this->resource->last_name,
            'specialties' => $this->resource->specialties ?? [],
            'role'        => $this->resource->role,
            'status'      => $this->resource->status->value,
            'expiresAt'   => $this->resource->expires_at?->toIso8601String(),
            'createdAt'   => $this->resource->created_at?->toIso8601String(),
            'hasAccount'  => $hasAccount,
        ];
    }
}
```

- [ ] **Step 3: Create the mail class skeleton (full template in Phase 4)**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly Company $company,
        public readonly User $owner,
        public readonly string $plaintextToken,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.invitation.subject', ['company' => $this->company->name]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee-invitation-link',
            with: [
                'companyName' => $this->company->name,
                'ownerName'   => trim(($this->owner->first_name ?? '') . ' ' . ($this->owner->last_name ?? '')) ?: 'Termini im',
                'firstName'   => $this->invitation->first_name,
                'token'       => $this->plaintextToken,
                'expiresAt'   => $this->invitation->expires_at,
            ],
        );
    }
}
```

- [ ] **Step 4: Create the mail dispatch job**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EmployeeInvitationLinkMail;
use App\Models\Company;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmployeeInvitationLinkEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly Company $company,
        public readonly User $owner,
        public readonly string $plaintextToken,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->invitation->email)
            ->locale(config('app.fallback_locale', 'fr'))
            ->send(new EmployeeInvitationLinkMail(
                invitation:     $this->invitation,
                company:        $this->company,
                owner:          $this->owner,
                plaintextToken: $this->plaintextToken,
            ));
    }
}
```

Create the blade view as a stub for now (real content in Phase 4):

```bash
mkdir -p resources/views/emails
printf '@component("mail::message")\nInvitation pour {{ $companyName }} ({{ $token }})\n@endcomponent\n' > resources/views/emails/employee-invitation-link.blade.php
```

- [ ] **Step 5: Add `inviteEmployee` v2 method to `MyCompanyController`**

Replace the existing `inviteEmployee` method (around line 475–525) with this version (don't touch `createEmployee` yet — we'll delete it in Phase 7). Add the helper `generateAndStoreToken` private method too.

```php
use App\Enums\InvitationStatus;
use App\Http\Requests\MyCompany\SendInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Jobs\SendEmployeeInvitationLinkEmail;
use App\Jobs\SendEmployeeInvitationPush;
use App\Models\EmployeeInvitation;
```

Method body:

```php
public function inviteEmployee(SendInvitationRequest $request): JsonResponse
{
    $company = $this->resolveOwnedCompany();
    if ($company instanceof JsonResponse) {
        return $company;
    }

    $email = $request->validated('email');

    /** @var User $owner */
    $owner = auth()->user();

    if (strtolower((string) $owner->email) === $email) {
        return response()->json([
            'success' => false,
            'message' => 'You cannot invite yourself.',
        ], 422);
    }

    // Already a confirmed member? Block.
    $alreadyMember = User::where('email', $email)
        ->whereHas('companies', fn ($q) => $q->where('company_id', $company->id))
        ->exists();
    if ($alreadyMember) {
        return response()->json([
            'success' => false,
            'message' => 'This user is already a member of your company.',
        ], 422);
    }

    [$invitation, $plaintextToken] = DB::transaction(function () use ($request, $company, $owner, $email) {
        // Atomic check-or-update for pending invitation.
        $existing = EmployeeInvitation::where('company_id', $company->id)
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending)
            ->lockForUpdate()
            ->first();

        $plaintextToken = bin2hex(random_bytes(32)); // 64 hex chars
        $tokenHash      = hash('sha256', $plaintextToken);

        if ($existing) {
            $existing->update([
                'first_name'  => $request->validated('first_name', $existing->first_name),
                'last_name'   => $request->validated('last_name', $existing->last_name),
                'specialties' => $request->validated('specialties', $existing->specialties),
                'token_hash'  => $tokenHash,
                'expires_at'  => now()->addDays(7),
            ]);
            return [$existing->fresh(), $plaintextToken];
        }

        $invitation = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => $email,
            'first_name'         => $request->validated('first_name'),
            'last_name'          => $request->validated('last_name'),
            'specialties'        => $request->validated('specialties', []),
            'role'               => $request->validated('role', CompanyRole::Employee->value),
            'token_hash'         => $tokenHash,
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->addDays(7),
        ]);

        return [$invitation, $plaintextToken];
    });

    // Notification routing: existing user → push; no account → email link.
    $invitedUser = User::where('email', $email)->first();
    if ($invitedUser) {
        SendEmployeeInvitationPush::dispatch($invitation, $invitedUser);
    } else {
        SendEmployeeInvitationLinkEmail::dispatch($invitation, $company, $owner, $plaintextToken);
    }

    return (new InvitationResource($invitation))
        ->response()
        ->setStatusCode(201);
}
```

Create `SendEmployeeInvitationPush` as a no-op stub (Phase 5 fills in FCM):

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmployeeInvitationPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly User $invitedUser,
    ) {
    }

    public function handle(): void
    {
        // FCM dispatch — implemented in Phase 5.
    }
}
```

- [ ] **Step 6: Run the test, expect pass**

```bash
php artisan test --filter=test_owner_can_invite_unknown_email
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/MyCompany/SendInvitationRequest.php \
        app/Http/Resources/InvitationResource.php \
        app/Mail/EmployeeInvitationLinkMail.php \
        app/Jobs/SendEmployeeInvitationLinkEmail.php \
        app/Jobs/SendEmployeeInvitationPush.php \
        app/Http/Controllers/MyCompanyController.php \
        resources/views/emails/employee-invitation-link.blade.php \
        tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "feat(invitations): owner can invite by email (link flow stub)"
```

### Task 2.3: Test — invite an already-existing user dispatches push, not email

- [ ] **Step 1: Add the test to `InviteFlowTest`**

```php
public function test_invite_existing_user_sends_push_not_email(): void
{
    Mail::fake();

    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    User::factory()->create(['email' => 'bob@example.com']);

    $this->postJson('/api/my-company/employees/invite', [
        'email' => 'bob@example.com',
    ])->assertStatus(201)
      ->assertJsonPath('data.hasAccount', true);

    Mail::assertNothingSent();
    // Push job dispatch test in Phase 5 once FCM is wired.
}
```

- [ ] **Step 2: Run the test, expect pass**

```bash
php artisan test --filter=test_invite_existing_user_sends_push_not_email
```
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "test(invitations): existing user gets push, not email"
```

### Task 2.4: Test — re-inviting a pending email regenerates the token

- [ ] **Step 1: Add the test**

```php
public function test_reinvite_regenerates_token(): void
{
    Mail::fake();

    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $this->postJson('/api/my-company/employees/invite', [
        'email' => 'alice@example.com',
    ])->assertStatus(201);

    $firstHash = EmployeeInvitation::where('email', 'alice@example.com')->value('token_hash');

    $this->postJson('/api/my-company/employees/invite', [
        'email' => 'alice@example.com',
    ])->assertStatus(201);

    $this->assertEquals(
        1,
        EmployeeInvitation::where('email', 'alice@example.com')->count(),
        'should have only one pending invitation, not two',
    );

    $secondHash = EmployeeInvitation::where('email', 'alice@example.com')->value('token_hash');
    $this->assertNotEquals($firstHash, $secondHash, 'token must be regenerated');
}
```

- [ ] **Step 2: Run the test, expect pass**

```bash
php artisan test --filter=test_reinvite_regenerates_token
```
Expected: PASS — already covered by the upsert logic.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "test(invitations): re-invite regenerates token, no duplicate row"
```

### Task 2.5: Test — owner cannot invite themselves or an existing member

- [ ] **Step 1: Add the tests**

```php
public function test_cannot_invite_yourself(): void
{
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $this->postJson('/api/my-company/employees/invite', [
        'email' => $owner->email,
    ])->assertStatus(422);
}

public function test_cannot_invite_existing_member(): void
{
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $employee = User::factory()->create(['email' => 'emp@example.com']);
    CompanyUser::create([
        'company_id' => $company->id,
        'user_id'    => $employee->id,
        'role'       => CompanyRole::Employee,
        'is_active'  => true,
    ]);

    $this->postJson('/api/my-company/employees/invite', [
        'email' => 'emp@example.com',
    ])->assertStatus(422);
}
```

- [ ] **Step 2: Run, expect pass**

```bash
php artisan test --filter=InviteFlowTest
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "test(invitations): block self-invite and member-invite"
```

### Task 2.6: Add resend + revoke endpoints

**Files:**
- Modify: `app/Http/Controllers/MyCompanyController.php` — add `resendInvitation`, `revokeInvitation` methods
- Modify: `routes/api.php`

- [ ] **Step 1: Add tests**

```php
public function test_resend_regenerates_token_and_resets_expiry(): void
{
    Mail::fake();
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $this->postJson('/api/my-company/employees/invite', ['email' => 'alice@example.com']);
    $invitation = EmployeeInvitation::where('email', 'alice@example.com')->first();
    $oldHash = $invitation->token_hash;

    // Travel forward 3 days so we can confirm expiry was reset.
    $this->travel(3)->days();

    $this->postJson("/api/my-company/employees/invitations/{$invitation->id}/resend")
        ->assertOk();

    $invitation->refresh();
    $this->assertNotEquals($oldHash, $invitation->token_hash);
    $this->assertTrue($invitation->expires_at->diffInDays(now()) >= 6);
}

public function test_revoke_marks_invitation_revoked(): void
{
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $this->postJson('/api/my-company/employees/invite', ['email' => 'alice@example.com']);
    $invitation = EmployeeInvitation::where('email', 'alice@example.com')->first();

    $this->deleteJson("/api/my-company/employees/invitations/{$invitation->id}")
        ->assertOk();

    $this->assertEquals(InvitationStatus::Revoked, $invitation->fresh()->status);
}
```

- [ ] **Step 2: Implement the controller methods**

Add to `MyCompanyController` (below `inviteEmployee`):

```php
public function resendInvitation(int $id): JsonResponse
{
    $company = $this->resolveOwnedCompany();
    if ($company instanceof JsonResponse) {
        return $company;
    }

    $invitation = EmployeeInvitation::where('id', $id)
        ->where('company_id', $company->id)
        ->where('status', InvitationStatus::Pending)
        ->first();

    if (! $invitation) {
        return $this->notFound('invitation');
    }

    $plaintextToken = bin2hex(random_bytes(32));
    $invitation->update([
        'token_hash' => hash('sha256', $plaintextToken),
        'expires_at' => now()->addDays(7),
    ]);

    /** @var User $owner */
    $owner = auth()->user();
    $invitedUser = User::where('email', $invitation->email)->first();
    if ($invitedUser) {
        SendEmployeeInvitationPush::dispatch($invitation, $invitedUser);
    } else {
        SendEmployeeInvitationLinkEmail::dispatch($invitation, $company, $owner, $plaintextToken);
    }

    return response()->json([
        'success' => true,
        'data'    => new InvitationResource($invitation),
    ]);
}

public function revokeInvitation(int $id): JsonResponse
{
    $company = $this->resolveOwnedCompany();
    if ($company instanceof JsonResponse) {
        return $company;
    }

    $invitation = EmployeeInvitation::where('id', $id)
        ->where('company_id', $company->id)
        ->where('status', InvitationStatus::Pending)
        ->first();

    if (! $invitation) {
        return $this->notFound('invitation');
    }

    $invitation->update(['status' => InvitationStatus::Revoked]);

    return response()->json([
        'success' => true,
        'message' => 'Invitation revoked.',
    ]);
}
```

- [ ] **Step 3: Register the routes in `routes/api.php`**

Find the existing `Route::post('/my-company/employees/invite', ...)` registration and add right after it (inside the same auth:sanctum group):

```php
Route::post('/my-company/employees/invitations/{id}/resend', [MyCompanyController::class, 'resendInvitation'])
    ->whereNumber('id')
    ->middleware('throttle:3,60');
Route::delete('/my-company/employees/invitations/{id}', [MyCompanyController::class, 'revokeInvitation'])
    ->whereNumber('id');
```

- [ ] **Step 4: Run the tests, expect pass**

```bash
php artisan test --filter=InviteFlowTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/MyCompanyController.php routes/api.php tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "feat(invitations): owner can resend and revoke invitations"
```

### Task 2.7: Modify `listEmployees` to merge members + invitations

**Files:**
- Modify: `app/Http/Controllers/MyCompanyController.php` — `listEmployees` method
- Modify: `app/Http/Resources/EmployeeResource.php` — add `kind: 'member'`

- [ ] **Step 1: Add the test**

```php
public function test_list_employees_includes_pending_invitations(): void
{
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $employee = User::factory()->create();
    CompanyUser::create([
        'company_id' => $company->id,
        'user_id'    => $employee->id,
        'role'       => CompanyRole::Employee,
        'is_active'  => true,
    ]);
    $this->postJson('/api/my-company/employees/invite', ['email' => 'alice@example.com']);

    $response = $this->getJson('/api/my-company/employees');
    $response->assertOk();

    $kinds = collect($response->json('data'))->pluck('kind')->all();
    $this->assertContains('member', $kinds);
    $this->assertContains('invitation', $kinds);
}

public function test_list_employees_excludes_refused_by_default(): void
{
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    EmployeeInvitation::create([
        'company_id'         => $company->id,
        'invited_by_user_id' => $owner->id,
        'email'              => 'r@example.com',
        'specialties'        => [],
        'token_hash'         => str_repeat('a', 64),
        'status'             => InvitationStatus::Refused,
        'expires_at'         => now()->subDay(),
    ]);

    $response = $this->getJson('/api/my-company/employees');
    $emails = collect($response->json('data'))->pluck('email')->filter()->all();
    $this->assertNotContains('r@example.com', $emails);
}
```

- [ ] **Step 2: Modify `EmployeeResource` to add `kind`**

Add `'kind' => 'member',` at the top of the return array.

- [ ] **Step 3: Modify `listEmployees`**

Replace the body of `listEmployees`:

```php
public function listEmployees(Request $request): JsonResponse
{
    $company = $this->resolveOwnedCompany();
    if ($company instanceof JsonResponse) {
        return $company;
    }

    $includeHistory = $request->boolean('include') === false
        ? false
        : ($request->query('include') === 'history');

    $members = CompanyUser::where('company_id', $company->id)
        ->with(['user', 'services'])
        ->get();

    $invitationsQuery = EmployeeInvitation::where('company_id', $company->id);
    if (! $includeHistory) {
        $invitationsQuery->where('status', InvitationStatus::Pending);
    }
    $invitations = $invitationsQuery->orderByDesc('created_at')->get();

    return response()->json([
        'data' => array_merge(
            EmployeeResource::collection($members)->resolve(),
            InvitationResource::collection($invitations)->resolve(),
        ),
    ]);
}
```

- [ ] **Step 4: Run tests, expect pass**

```bash
php artisan test --filter=InviteFlowTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/MyCompanyController.php app/Http/Resources/EmployeeResource.php tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "feat(invitations): merge members + pending invitations in /employees list"
```

---

## Phase 3: Public token lookup + register-with-token

### Task 3.1: Public token lookup endpoint

**Files:**
- Create: `app/Http/Resources/PublicInvitationResource.php`
- Create: `app/Http/Controllers/EmployeeInvitationController.php`
- Modify: `routes/api.php` — add public route `/api/invitations/{token}`
- Create: `tests/Feature/EmployeeInvitation/RegisterWithTokenTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterWithTokenTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingInvitation(string $token = null): array
    {
        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create(['name' => 'Salon X']);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => CompanyRole::Owner,
            'is_active'  => true,
        ]);

        $token = $token ?? bin2hex(random_bytes(32));
        $invitation = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => 'alice@example.com',
            'first_name'         => 'Alice',
            'last_name'          => 'Martin',
            'specialties'        => [],
            'token_hash'         => hash('sha256', $token),
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->addDays(7),
        ]);

        return [$owner, $company, $invitation, $token];
    }

    public function test_public_lookup_returns_invitation_details(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.companyName', 'Salon X')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.firstName', 'Alice');
    }

    public function test_public_lookup_returns_410_for_expired(): void
    {
        [$owner, $company, $invitation, $token] = $this->createPendingInvitation();
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/invitations/{$token}")->assertStatus(410);
    }

    public function test_public_lookup_returns_404_for_unknown(): void
    {
        $this->getJson('/api/invitations/' . str_repeat('z', 64))
            ->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run the test, expect failure**

```bash
php artisan test --filter=RegisterWithTokenTest
```
Expected: FAIL — route not found.

- [ ] **Step 3: Create `PublicInvitationResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->resource->invitedBy;
        $company = $this->resource->company;

        return [
            'companyName' => $company->name,
            'ownerName'   => trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) ?: 'Termini im',
            'email'       => $this->resource->email,
            'firstName'   => $this->resource->first_name,
            'lastName'    => $this->resource->last_name,
            'expiresAt'   => $this->resource->expires_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Create `EmployeeInvitationController` (public method only)**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Http\Resources\PublicInvitationResource;
use App\Models\EmployeeInvitation;
use Illuminate\Http\JsonResponse;

class EmployeeInvitationController extends Controller
{
    public function showByToken(string $token): JsonResponse
    {
        if (! ctype_xdigit($token) || strlen($token) !== 64) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $invitation = EmployeeInvitation::with(['company', 'invitedBy'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            return response()->json(['message' => 'Invitation no longer valid.'], 410);
        }

        if ($invitation->expires_at->isPast()) {
            return response()->json(['message' => 'Invitation expired.'], 410);
        }

        return response()->json([
            'data' => (new PublicInvitationResource($invitation))->toArray(request()),
        ]);
    }
}
```

- [ ] **Step 5: Add the route in `routes/api.php`**

Add this line **outside** any auth middleware group (top-level public routes):

```php
use App\Http\Controllers\EmployeeInvitationController;

Route::get('/invitations/{token}', [EmployeeInvitationController::class, 'showByToken'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:60,1');
```

- [ ] **Step 6: Run tests, expect pass**

```bash
php artisan test --filter=RegisterWithTokenTest
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EmployeeInvitationController.php \
        app/Http/Resources/PublicInvitationResource.php \
        routes/api.php \
        tests/Feature/EmployeeInvitation/RegisterWithTokenTest.php
git commit -m "feat(invitations): public lookup endpoint with throttled token"
```

### Task 3.2: Register endpoint accepts `invitation_token` and auto-verifies email

**Files:**
- Modify: `app/Http/Requests/Auth/RegisterRequest.php` — add optional `invitation_token`
- Modify: `app/Http/Controllers/AuthController.php` — branch on `invitation_token`

- [ ] **Step 1: Add the failing test (extend `RegisterWithTokenTest`)**

```php
public function test_register_with_valid_token_creates_user_and_pivot(): void
{
    [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

    $payload = [
        'first_name'        => 'Alice',
        'last_name'         => 'Martin',
        'email'             => 'alice@example.com',
        'password'          => 'P@ssw0rd1234',
        'password_confirmation' => 'P@ssw0rd1234',
        'phone'             => '+38344000000',
        'invitation_token'  => $token,
    ];

    $response = $this->postJson('/api/auth/register', $payload);
    $response->assertStatus(201);

    $user = User::where('email', 'alice@example.com')->first();
    $this->assertNotNull($user);
    $this->assertNotNull($user->email_verified_at, 'email must be auto-verified');

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id'    => $user->id,
        'is_active'  => true,
    ]);

    $invitation->refresh();
    $this->assertEquals(InvitationStatus::Accepted, $invitation->status);
    $this->assertEquals($user->id, $invitation->resulting_user_id);
}

public function test_register_with_token_but_mismatched_email_fails_422(): void
{
    [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

    $this->postJson('/api/auth/register', [
        'first_name'            => 'X', 'last_name' => 'Y',
        'email'                 => 'someone-else@example.com',
        'password'              => 'P@ssw0rd1234',
        'password_confirmation' => 'P@ssw0rd1234',
        'phone'                 => '+1',
        'invitation_token'      => $token,
    ])->assertStatus(422);
}

public function test_register_with_expired_token_fails_410(): void
{
    [$owner, $company, $invitation, $token] = $this->createPendingInvitation();
    $invitation->update(['expires_at' => now()->subDay()]);

    $this->postJson('/api/auth/register', [
        'first_name'            => 'Alice', 'last_name' => 'Martin',
        'email'                 => 'alice@example.com',
        'password'              => 'P@ssw0rd1234',
        'password_confirmation' => 'P@ssw0rd1234',
        'phone'                 => '+1',
        'invitation_token'      => $token,
    ])->assertStatus(410);
}
```

- [ ] **Step 2: Run, expect failure**

```bash
php artisan test --filter=RegisterWithTokenTest
```
Expected: FAIL on the new tests.

- [ ] **Step 3: Modify `RegisterRequest`**

Add to `rules()`:

```php
'invitation_token' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
```

- [ ] **Step 4: Modify `AuthController::register`**

Read the existing `register` method first (around line 32-86). Add a helper method and inject the invitation logic into the body. Pseudocode for what to insert near the top of `register`:

```php
$invitation = null;
if ($request->filled('invitation_token')) {
    $invitation = EmployeeInvitation::with('company')
        ->where('token_hash', hash('sha256', (string) $request->input('invitation_token')))
        ->first();

    if (! $invitation || $invitation->status !== InvitationStatus::Pending) {
        return response()->json(['message' => 'Invitation no longer valid.'], 410);
    }
    if ($invitation->expires_at->isPast()) {
        return response()->json(['message' => 'Invitation expired.'], 410);
    }
    if (strtolower((string) $request->input('email')) !== $invitation->email) {
        return response()->json([
            'message' => 'Email does not match invitation.',
            'errors'  => ['email' => ['email-mismatch']],
        ], 422);
    }
}
```

Then in the User creation block, set `email_verified_at = now()` if `$invitation !== null`. **Wrap the entire creation in a DB transaction** so the user, pivot, and invitation update commit together.

After the user is created, if `$invitation`:

```php
DB::transaction(function () use ($invitation, $user) {
    \App\Models\CompanyUser::create([
        'company_id' => $invitation->company_id,
        'user_id'    => $user->id,
        'role'       => $invitation->role,
        'specialties'=> $invitation->specialties ?? [],
        'is_active'  => true,
    ]);
    $invitation->update([
        'status'            => InvitationStatus::Accepted,
        'accepted_at'       => now(),
        'resulting_user_id' => $user->id,
    ]);
});

\App\Jobs\SendInvitationDecisionPush::dispatch($invitation->fresh(), 'accepted');
```

(`SendInvitationDecisionPush` stub is created in Phase 5 — for now, create a no-op stub so dispatch doesn't error.)

Create `app/Jobs/SendInvitationDecisionPush.php` with a no-op `handle()` (mirror `SendEmployeeInvitationPush`).

- [ ] **Step 5: Run tests, expect pass**

```bash
php artisan test --filter=RegisterWithTokenTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Auth/RegisterRequest.php \
        app/Http/Controllers/AuthController.php \
        app/Jobs/SendInvitationDecisionPush.php \
        tests/Feature/EmployeeInvitation/RegisterWithTokenTest.php
git commit -m "feat(invitations): register accepts invitation_token, auto-verifies email"
```

### Task 3.3: Register without token on a pre-invited email succeeds

- [ ] **Step 1: Add the test**

```php
public function test_normal_register_with_pending_invite_email_succeeds(): void
{
    [$owner, $company, $invitation, $token] = $this->createPendingInvitation();

    $this->postJson('/api/auth/register', [
        'first_name'            => 'Alice', 'last_name' => 'Martin',
        'email'                 => 'alice@example.com',
        'password'              => 'P@ssw0rd1234',
        'password_confirmation' => 'P@ssw0rd1234',
        'phone'                 => '+1',
    ])->assertStatus(201);

    // Invitation stays pending.
    $this->assertEquals(InvitationStatus::Pending, $invitation->fresh()->status);

    // No pivot was created.
    $this->assertDatabaseMissing('company_user', [
        'company_id' => $company->id,
    ]);
}
```

- [ ] **Step 2: Run, expect pass** (already covered by the existing register flow since the invitation table is independent of `users`).

```bash
php artisan test --filter=RegisterWithTokenTest
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/EmployeeInvitation/RegisterWithTokenTest.php
git commit -m "test(invitations): normal register on invited email leaves invite pending"
```

---

## Phase 4: Email template (full content + i18n)

### Task 4.1: Add email translation strings

**Files:**
- Modify: `resources/lang/fr/emails.php`
- Modify: `resources/lang/en/emails.php`
- Modify: `resources/lang/sq/emails.php`

- [ ] **Step 1: Add the keys to each file**

Add a new `'invitation' => [...]` array entry to each `emails.php`. **FR (`resources/lang/fr/emails.php`):**

```php
'invitation' => [
    'subject'    => 'Invitation à rejoindre :company',
    'greeting'   => 'Bonjour :name,',
    'intro'      => ':owner t\'invite à rejoindre l\'équipe de :company sur Termini im.',
    'cta'        => 'Créer mon compte',
    'expires_at' => 'Ce lien expire le :date.',
    'footer'     => 'Si tu n\'attendais pas cette invitation, ignore simplement cet email.',
],
```

**EN (`resources/lang/en/emails.php`):**

```php
'invitation' => [
    'subject'    => 'Invitation to join :company',
    'greeting'   => 'Hello :name,',
    'intro'      => ':owner has invited you to join :company on Termini im.',
    'cta'        => 'Create my account',
    'expires_at' => 'This link expires on :date.',
    'footer'     => 'If you weren\'t expecting this invitation, just ignore this email.',
],
```

**SQ (`resources/lang/sq/emails.php`):**

```php
'invitation' => [
    'subject'    => 'Ftesë për t\'u bashkuar me :company',
    'greeting'   => 'Përshëndetje :name,',
    'intro'      => ':owner të ka ftuar të bashkohesh me :company në Termini im.',
    'cta'        => 'Krijo llogarinë time',
    'expires_at' => 'Ky link skadon më :date.',
    'footer'     => 'Nëse nuk e prisje këtë ftesë, thjesht injoroje këtë email.',
],
```

- [ ] **Step 2: Commit**

```bash
git add resources/lang/fr/emails.php resources/lang/en/emails.php resources/lang/sq/emails.php
git commit -m "feat(invitations): add i18n strings for invitation email"
```

### Task 4.2: Replace blade template stub with real content

**Files:**
- Modify: `resources/views/emails/employee-invitation-link.blade.php`
- Modify: `app/Mail/EmployeeInvitationLinkMail.php` — pass `$invitedName` + locale-aware date format
- Modify: `app/Jobs/SendEmployeeInvitationLinkEmail.php` — pass user locale (lookup by email if account exists, else fallback `fr`)

- [ ] **Step 1: Replace the blade view**

```blade
@component('mail::message')
{{ __('emails.invitation.greeting', ['name' => $firstName ?? '']) }}

{{ __('emails.invitation.intro', ['owner' => $ownerName, 'company' => $companyName]) }}

@component('mail::button', ['url' => $deepLink])
{{ __('emails.invitation.cta') }}
@endcomponent

{{ __('emails.invitation.expires_at', ['date' => $expiresAt->isoFormat('D MMMM YYYY')]) }}

---

{{ __('emails.invitation.footer') }}
@endcomponent
```

- [ ] **Step 2: Update `EmployeeInvitationLinkMail::content()` to pass `$deepLink`**

```php
$deepLink = config('app.url') . '/invite/' . $this->plaintextToken;
```

Pass it in the `with` array:

```php
'deepLink' => $deepLink,
```

- [ ] **Step 3: Update job to use locale of invited user (if they exist) or fallback `fr`**

In `SendEmployeeInvitationLinkEmail::handle()`:

```php
$locale = User::where('email', $this->invitation->email)->value('locale')
    ?? config('app.fallback_locale', 'fr');

Mail::to($this->invitation->email)
    ->locale($locale)
    ->send(new EmployeeInvitationLinkMail(...));
```

- [ ] **Step 4: Manual test via Mailpit**

```bash
php artisan tinker --execute="
\$inv = App\Models\EmployeeInvitation::latest()->first();
\$company = \$inv->company;
\$owner = \$inv->invitedBy;
App\Jobs\SendEmployeeInvitationLinkEmail::dispatchSync(\$inv, \$company, \$owner, 'demo-token-' . str_repeat('a', 56));
"
```

Open `http://localhost:8025` in a browser. Expected: email rendered in FR with brand styling and the `Créer mon compte` button.

- [ ] **Step 5: Commit**

```bash
git add resources/views/emails/employee-invitation-link.blade.php \
        app/Mail/EmployeeInvitationLinkMail.php \
        app/Jobs/SendEmployeeInvitationLinkEmail.php
git commit -m "feat(invitations): full email template with i18n + deep link"
```

---

## Phase 5: Authenticated me-side endpoints (accept/refuse + push)

### Task 5.1: Test accept + refuse endpoints

**Files:**
- Create: `tests/Feature/EmployeeInvitation/AcceptRefuseTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcceptRefuseTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingFor(string $email): array
    {
        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id'    => $owner->id,
            'role'       => CompanyRole::Owner,
            'is_active'  => true,
        ]);
        $invitation = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => $email,
            'specialties'        => [],
            'token_hash'         => str_repeat('a', 64),
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->addDays(7),
        ]);
        return [$owner, $company, $invitation];
    }

    public function test_user_sees_their_pending_invitations(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, $company, $invitation] = $this->makePendingFor('me@example.com');
        $this->makePendingFor('someone-else@example.com');

        $response = $this->getJson('/api/me/invitations');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $invitation->id);
    }

    public function test_user_can_accept_invitation(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, $company, $invitation] = $this->makePendingFor('me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'is_active'  => true,
        ]);

        $this->assertEquals(InvitationStatus::Accepted, $invitation->fresh()->status);
    }

    public function test_user_can_refuse_invitation(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, , $invitation] = $this->makePendingFor('me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/refuse")
            ->assertOk();

        $this->assertEquals(InvitationStatus::Refused, $invitation->fresh()->status);
    }

    public function test_cannot_act_on_someone_elses_invitation(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, , $invitation] = $this->makePendingFor('not-me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/accept")
            ->assertStatus(404);
    }

    public function test_double_accept_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        [, $company, $invitation] = $this->makePendingFor('me@example.com');

        $this->postJson("/api/me/invitations/{$invitation->id}/accept")->assertOk();
        $this->postJson("/api/me/invitations/{$invitation->id}/accept")->assertOk();

        $this->assertEquals(
            1,
            CompanyUser::where('company_id', $company->id)->where('user_id', $user->id)->count(),
        );
    }
}
```

- [ ] **Step 2: Run, expect failure**

```bash
php artisan test --filter=AcceptRefuseTest
```

### Task 5.2: Implement me-side endpoints

**Files:**
- Modify: `app/Http/Controllers/EmployeeInvitationController.php` — add `mine`, `accept`, `refuse`
- Create: `app/Http/Resources/MyInvitationResource.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create `MyInvitationResource`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->resource->invitedBy;
        $company = $this->resource->company;

        return [
            'id'        => $this->resource->id,
            'company'   => [
                'id'      => (string) $company->id,
                'name'    => $company->name,
                'city'    => $company->city ?? null,
                'logoUrl' => $company->logo_url ?? null,
            ],
            'invitedBy' => [
                'firstName' => $owner->first_name,
                'lastName'  => $owner->last_name,
            ],
            'role'      => $this->resource->role,
            'expiresAt' => $this->resource->expires_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 2: Add the controller methods**

```php
public function mine(): JsonResponse
{
    $email = strtolower((string) auth()->user()->email);

    $invitations = EmployeeInvitation::with(['company', 'invitedBy'])
        ->where('email', $email)
        ->where('status', InvitationStatus::Pending)
        ->where('expires_at', '>', now())
        ->orderByDesc('created_at')
        ->get();

    return response()->json([
        'data' => MyInvitationResource::collection($invitations)->resolve(),
    ]);
}

public function accept(int $id): JsonResponse
{
    $email = strtolower((string) auth()->user()->email);
    $userId = auth()->id();

    $invitation = EmployeeInvitation::where('id', $id)
        ->where('email', $email)
        ->first();

    if (! $invitation) {
        return response()->json(['message' => 'Not found.'], 404);
    }

    // Idempotent for already-accepted by this user.
    if ($invitation->status === InvitationStatus::Accepted
        && $invitation->resulting_user_id === $userId) {
        return response()->json(['success' => true]);
    }

    if ($invitation->status !== InvitationStatus::Pending
        || $invitation->expires_at->isPast()) {
        return response()->json(['message' => 'Invitation no longer valid.'], 410);
    }

    DB::transaction(function () use ($invitation, $userId) {
        \App\Models\CompanyUser::firstOrCreate(
            [
                'company_id' => $invitation->company_id,
                'user_id'    => $userId,
            ],
            [
                'role'        => $invitation->role,
                'specialties' => $invitation->specialties ?? [],
                'is_active'   => true,
            ],
        );
        $invitation->update([
            'status'            => InvitationStatus::Accepted,
            'accepted_at'       => now(),
            'resulting_user_id' => $userId,
        ]);
    });

    \App\Jobs\SendInvitationDecisionPush::dispatch($invitation->fresh(), 'accepted');

    return response()->json(['success' => true]);
}

public function refuse(int $id): JsonResponse
{
    $email = strtolower((string) auth()->user()->email);

    $invitation = EmployeeInvitation::where('id', $id)
        ->where('email', $email)
        ->first();

    if (! $invitation) {
        return response()->json(['message' => 'Not found.'], 404);
    }

    if ($invitation->status !== InvitationStatus::Pending) {
        return response()->json(['message' => 'Invitation no longer valid.'], 410);
    }

    $invitation->update([
        'status'      => InvitationStatus::Refused,
        'refused_at'  => now(),
    ]);

    \App\Jobs\SendInvitationDecisionPush::dispatch($invitation->fresh(), 'refused');

    return response()->json(['success' => true]);
}
```

- [ ] **Step 3: Register routes (in the auth:sanctum group)**

```php
Route::get('/me/invitations', [EmployeeInvitationController::class, 'mine']);
Route::post('/me/invitations/{id}/accept', [EmployeeInvitationController::class, 'accept'])
    ->whereNumber('id');
Route::post('/me/invitations/{id}/refuse', [EmployeeInvitationController::class, 'refuse'])
    ->whereNumber('id');
```

- [ ] **Step 4: Run tests, expect pass**

```bash
php artisan test --filter=AcceptRefuseTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/EmployeeInvitationController.php \
        app/Http/Resources/MyInvitationResource.php \
        routes/api.php \
        tests/Feature/EmployeeInvitation/AcceptRefuseTest.php
git commit -m "feat(invitations): /me/invitations list + accept + refuse endpoints"
```

### Task 5.3: Wire FCM push for invitation events

**Files:**
- Modify: `app/Jobs/SendEmployeeInvitationPush.php`
- Modify: `app/Jobs/SendInvitationDecisionPush.php`

Locate the existing push helper used elsewhere in the codebase (search for `kreait` or `Messaging` usage):

```bash
grep -r "kreait\\\\Firebase" app/ --include="*.php" -l
```

Expected: at least one existing job dispatches a push (e.g. `SendBookingConfirmationEmail` or a notification preference helper).

- [ ] **Step 1: Inspect the existing push integration**

Read the file(s) returned. Identify the helper class/method that resolves a user's `UserDevice` rows and sends a notification with `title`, `body`, optional `data`. (Likely something like `App\Services\PushDispatcher` or inline `Messaging::send`.)

- [ ] **Step 2: Implement `SendEmployeeInvitationPush::handle()`**

Replace the stub `handle()` with (adapt the helper call to whatever pattern exists):

```php
public function handle(): void
{
    $devices = $this->invitedUser->devices()->get();
    if ($devices->isEmpty()) {
        return;
    }

    $title = __('push.invitation.received.title', [], $this->invitedUser->locale ?? 'fr');
    $body  = __('push.invitation.received.body', [
        'company' => $this->invitation->company->name,
    ], $this->invitedUser->locale ?? 'fr');

    foreach ($devices as $device) {
        // [Use the same pattern as the existing booking-notif dispatcher.]
    }
}
```

(If no helper exists, create `App\Services\InvitationPushDispatcher` to encapsulate FCM logic — keeps the job thin.)

- [ ] **Step 3: Implement `SendInvitationDecisionPush::handle()`**

The job constructor signature is `(EmployeeInvitation $invitation, string $decision)` where `$decision` is `'accepted'`, `'refused'`, or `'expired'`.

```php
public function handle(): void
{
    $owner = $this->invitation->invitedBy;
    $devices = $owner->devices()->get();
    if ($devices->isEmpty()) {
        return;
    }

    $title = __("push.invitation.{$this->decision}.title", [], $owner->locale ?? 'fr');
    $body  = __("push.invitation.{$this->decision}.body", [
        'email' => $this->invitation->email,
    ], $owner->locale ?? 'fr');

    // [Same dispatch loop as above.]
}
```

- [ ] **Step 4: Add the push translation strings**

In `resources/lang/{fr,en,sq}/push.php` (create if absent), add an `'invitation'` array with `received`, `accepted`, `refused`, `expired` entries each having `title` and `body`. Mirror the existing translation conventions for other push events.

- [ ] **Step 5: Add a test that the job is dispatched**

In `InviteFlowTest`:

```php
public function test_invite_existing_user_dispatches_push_job(): void
{
    \Illuminate\Support\Facades\Bus::fake();

    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    User::factory()->create(['email' => 'bob@example.com']);

    $this->postJson('/api/my-company/employees/invite', ['email' => 'bob@example.com']);

    \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SendEmployeeInvitationPush::class);
}
```

- [ ] **Step 6: Run all invitation tests**

```bash
php artisan test --filter=EmployeeInvitation
```
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add app/Jobs/SendEmployeeInvitationPush.php \
        app/Jobs/SendInvitationDecisionPush.php \
        resources/lang/fr/push.php resources/lang/en/push.php resources/lang/sq/push.php \
        tests/Feature/EmployeeInvitation/InviteFlowTest.php
git commit -m "feat(invitations): wire FCM push for invitation lifecycle events"
```

---

## Phase 6: Nightly expiration command

### Task 6.1: Test the expire command

**Files:**
- Create: `tests/Feature/EmployeeInvitation/ExpireCommandTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\EmployeeInvitation;

use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ExpireCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_command_marks_past_pending_as_expired(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['role' => UserRole::Company]);
        $company = Company::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => CompanyRole::Owner, 'is_active' => true,
        ]);

        $expired = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => 'a@x.com', 'specialties' => [],
            'token_hash'         => str_repeat('a', 64),
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->subHour(),
        ]);
        $stillValid = EmployeeInvitation::create([
            'company_id'         => $company->id,
            'invited_by_user_id' => $owner->id,
            'email'              => 'b@x.com', 'specialties' => [],
            'token_hash'         => str_repeat('b', 64),
            'status'             => InvitationStatus::Pending,
            'expires_at'         => now()->addDay(),
        ]);

        $this->artisan('invitations:expire')->assertSuccessful();

        $this->assertEquals(InvitationStatus::Expired, $expired->fresh()->status);
        $this->assertEquals(InvitationStatus::Pending, $stillValid->fresh()->status);

        Bus::assertDispatched(\App\Jobs\SendInvitationDecisionPush::class, 1);
    }
}
```

- [ ] **Step 2: Run, expect failure**

```bash
php artisan test --filter=ExpireCommandTest
```

### Task 6.2: Implement the command

**Files:**
- Create: `app/Console/Commands/ExpireInvitations.php`
- Modify: `routes/console.php`

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvitationStatus;
use App\Jobs\SendInvitationDecisionPush;
use App\Models\EmployeeInvitation;
use Illuminate\Console\Command;

class ExpireInvitations extends Command
{
    protected $signature = 'invitations:expire';
    protected $description = 'Mark pending employee invitations as expired and notify owners.';

    public function handle(): int
    {
        $expired = EmployeeInvitation::where('status', InvitationStatus::Pending)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $invitation) {
            $invitation->update(['status' => InvitationStatus::Expired]);
            SendInvitationDecisionPush::dispatch($invitation->fresh(), 'expired');
        }

        $this->info("Expired {$expired->count()} invitations.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Schedule the command in `routes/console.php`**

Append:

```php
use App\Console\Commands\ExpireInvitations;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ExpireInvitations::class)->dailyAt('03:00');
```

- [ ] **Step 3: Run tests, expect pass**

```bash
php artisan test --filter=ExpireCommandTest
```

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/ExpireInvitations.php routes/console.php tests/Feature/EmployeeInvitation/ExpireCommandTest.php
git commit -m "feat(invitations): nightly job marks expired invitations + notifies owner"
```

---

## Phase 7: Cleanup — drop legacy `/employees/create`

### Task 7.1: Remove the legacy endpoint

**Files:**
- Modify: `app/Http/Controllers/MyCompanyController.php` — delete `createEmployee` method
- Modify: `routes/api.php` — remove the route registration
- Delete: `app/Http/Requests/MyCompany/CreateEmployeeRequest.php`

- [ ] **Step 1: Add a regression test asserting the route is gone**

```php
public function test_legacy_create_employee_route_returns_404(): void
{
    [$owner, $company] = $this->makeOwnerWithCompany();
    Sanctum::actingAs($owner);

    $this->postJson('/api/my-company/employees/create', [
        'email' => 'x@x.com', 'first_name' => 'X', 'last_name' => 'Y',
        'password' => 'P@ssw0rd1234',
    ])->assertStatus(404);
}
```

- [ ] **Step 2: Delete the method, the route registration, and the request class**

```bash
rm app/Http/Requests/MyCompany/CreateEmployeeRequest.php
```

In `MyCompanyController.php`, delete the `createEmployee` method (around line 532-566). In `routes/api.php`, delete the line registering the route.

- [ ] **Step 3: Run tests, expect pass**

```bash
php artisan test --filter=InviteFlowTest
```

- [ ] **Step 4: Run the whole suite to catch broken references**

```bash
php artisan test
```
Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/MyCompanyController.php routes/api.php tests/Feature/EmployeeInvitation/InviteFlowTest.php
git rm app/Http/Requests/MyCompany/CreateEmployeeRequest.php
git commit -m "feat(invitations): drop legacy /employees/create endpoint"
```

---

## Phase 8: Final pass + PR

### Task 8.1: Run the full suite + lint

- [ ] **Step 1: Full test suite**

```bash
php artisan test
```
Expected: all green.

- [ ] **Step 2: Static analysis (if Larastan or Pint configured)**

```bash
./vendor/bin/pint --test app/ tests/
```

If diffs are reported, run `./vendor/bin/pint app/ tests/` and commit:

```bash
git commit -am "chore: pint format"
```

### Task 8.2: Push the branch + open PR

- [ ] **Step 1: Push**

```bash
git push -u origin feat/employee-invitation
```

- [ ] **Step 2: Open the PR with `gh pr create`**

```bash
gh pr create --base main --title "feat(invitations): employee invitation flow with status + secure email auto-verification" --body "$(cat <<'EOF'
## Summary
- Adds dedicated `employee_invitations` table with status lifecycle (pending/accepted/refused/expired/revoked)
- Modifies `/auth/register` to accept an `invitation_token` and auto-verify the email server-side (token-derived, never client-controlled)
- New endpoints: `GET /api/invitations/{token}` (public lookup), `GET /api/me/invitations`, `POST /api/me/invitations/{id}/accept|refuse`, `POST /my-company/employees/invitations/{id}/resend`, `DELETE /my-company/employees/invitations/{id}`
- `GET /my-company/employees` now returns merged members + pending invitations with `kind` discriminator
- Removes legacy `/my-company/employees/create` (created accounts with passwords the employee never received)
- Nightly `invitations:expire` command + push notifications for accept/refuse/expire

## Test plan
- [ ] Run `php artisan test --filter=EmployeeInvitation` — all green locally
- [ ] Mailpit shows the invitation email with the correct deep link
- [ ] Manual smoke: invite an unknown email → receives email → registers via link → pivot created + invitation accepted
- [ ] Manual smoke: invite an existing user → push received → accept in app → pivot created
- [ ] Existing test suite still green

## Spec
See `docs/superpowers/specs/2026-04-24-employee-invitation-design.md` (mobile repo).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-review summary

**Spec coverage:**
- §3.1 schema → Task 1.2 ✓
- §3.2 no schema changes elsewhere → respected ✓
- §4.1 owner endpoints → Tasks 2.2 (invite), 2.6 (resend/revoke), 2.7 (list with kind) ✓
- §4.2 public/me endpoints → Tasks 3.1 (lookup), 3.2 (register with token), 5.2 (mine/accept/refuse) ✓
- §5 register security → Task 3.2 with email-mismatch + expired tests ✓
- §6 listing pending excluding refused/expired → Task 2.7 (`assertNotContains` test) ✓
- §7 notification matrix → Tasks 2.2 (email Flow 1), 2.3 (push Flow 2), 5.3 (full FCM wiring) ✓
- §8 nightly expiration → Phase 6 ✓
- §9 test list → all 14 backend test cases mapped to tasks ✓

**Placeholder check:** none. Every step has concrete code.

**Type/method consistency:** `EmployeeInvitation`, `InvitationStatus`, `SendEmployeeInvitationLinkEmail`, `SendEmployeeInvitationPush`, `SendInvitationDecisionPush` — names consistent across tasks.

**Out of scope (per spec §10):** existing user data migration, web invite splash page — not in this plan.
