<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Enums\AppointmentStatus;
use App\Enums\BookingMode;
use App\Http\Requests\MyCompany\CreateEmployeeRequest;
use App\Http\Requests\MyCompany\InviteEmployeeRequest;
use App\Http\Requests\MyCompany\StoreCategoryRequest;
use App\Http\Requests\MyCompany\StoreCapacityOverrideRequest;
use App\Http\Requests\MyCompany\StoreCompanyBreakRequest;
use App\Http\Requests\MyCompany\StoreServiceRequest;
use App\Http\Requests\MyCompany\StoreCompanyWalkInRequest;
use App\Http\Requests\MyCompany\UpdateAppointmentStatusRequest;
use App\Http\Requests\MyCompany\UpdateBookingSettingsRequest;
use App\Http\Requests\MyCompany\UpdateCapacityOverrideRequest;
use App\Http\Requests\MyCompany\UpdateCategoryRequest;
use App\Http\Requests\MyCompany\UpdateCompanyBreakRequest;
use App\Http\Requests\MyCompany\UpdateCompanyRequest;
use App\Http\Requests\MyCompany\UpdateEmployeeRequest;
use App\Http\Requests\MyCompany\UpdateOpeningHoursRequest;
use App\Http\Requests\MyCompany\UpdateServiceRequest;
use App\Http\Resources\CompanyBreakResource;
use App\Http\Resources\CompanyCapacityOverrideResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\MyCompanyResource;
use App\Http\Resources\OpeningHourResource;
use App\Http\Resources\OwnerAppointmentResource;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\Appointment;
use App\Models\Company;
use Carbon\Carbon;
use App\Models\CompanyBreak;
use App\Models\CompanyCapacityOverride;
use App\Models\CompanyDayOff;
use App\Models\CompanyOpeningHour;
use App\Models\CompanyUser;
use App\Models\EmployeeBreak;
use App\Models\EmployeeDayOff;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MyCompanyController extends Controller
{
    // -------------------------------------------------------------------------
    // Private helper — resolve and verify company ownership
    // -------------------------------------------------------------------------

    /**
     * Returns the Company owned by the authenticated user, or a 403/404 response.
     * The caller must check `instanceof JsonResponse` before proceeding.
     */
    private function resolveOwnedCompany(): Company|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = CompanyUser::where('user_id', $user->id)
            ->where('role', CompanyRole::Owner->value)
            ->first();

        if (! $pivot) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own a company.',
            ], 403);
        }

        $company = Company::find($pivot->company_id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        return $company;
    }

    /**
     * Resolves company access for endpoints that BOTH owners and employees
     * should reach (like the shared planning). Returns:
     *   - `company`       : the Company model
     *   - `companyUserId` : the employee's pivot id, or null for owners
     *   - `isOwner`       : true when the caller owns the company
     *
     * Employees see only their own bookings (filtered by companyUserId);
     * owners see the whole company calendar. Callers that need strict
     * owner-only access should keep using `resolveOwnedCompany()`.
     */
    private function resolveCompanyAccess(): array|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Prefer the owner pivot when the same user holds both roles on
        // different companies (rare but possible). Owners get full access.
        $ownerPivot = CompanyUser::where('user_id', $user->id)
            ->where('role', CompanyRole::Owner->value)
            ->first();
        if ($ownerPivot) {
            $company = Company::find($ownerPivot->company_id);
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found.',
                ], 404);
            }
            return [
                'company'       => $company,
                'companyUserId' => null,
                'isOwner'       => true,
            ];
        }

        // Fall back to employee pivot — scoped access only.
        $employeePivot = CompanyUser::where('user_id', $user->id)
            ->where('role', CompanyRole::Employee->value)
            ->where('is_active', true)
            ->first();
        if ($employeePivot) {
            $company = Company::find($employeePivot->company_id);
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found.',
                ], 404);
            }
            return [
                'company'       => $company,
                'companyUserId' => $employeePivot->id,
                'isOwner'       => false,
            ];
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not linked to any company.',
        ], 403);
    }

    /**
     * Verify a resource (category / service / employee) belongs to the company.
     */
    private function notFound(string $resource): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => ucfirst($resource) . ' not found.',
        ], 404);
    }

    // =========================================================================
    // Company Profile
    // =========================================================================

    /**
     * GET /api/my-company
     */
    public function show(): MyCompanyResource|JsonResponse
    {
        // Shared by owners and employees so the planning UI (now unified)
        // can read `bookingMode` and the salon's opening hours regardless
        // of role. The MyCompanyResource is safe to return to an employee —
        // it doesn't expose anything more sensitive than what they already
        // see on the public /companies/:id page.
        $access = $this->resolveCompanyAccess();
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $company = Company::select('*', DB::raw('ST_X(location) AS longitude'), DB::raw('ST_Y(location) AS latitude'))
            ->find($access['company']->id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $company->load('openingHours');

        return new MyCompanyResource($company);
    }

    /**
     * PUT /api/my-company
     */
    public function update(UpdateCompanyRequest $request): MyCompanyResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $validated = $request->validated();
        $lat = $validated['latitude'] ?? null;
        $lng = $validated['longitude'] ?? null;
        unset($validated['latitude'], $validated['longitude']);

        $company->update($validated);

        if ($lat !== null && $lng !== null) {
            DB::statement(
                'UPDATE companies SET location = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
                [(float) $lat, (float) $lng, $company->id]
            );
        }

        Cache::forget("company:detail:{$company->id}");

        return new MyCompanyResource($company->fresh('openingHours'));
    }

    // =========================================================================
    // Service Categories
    // =========================================================================

    /**
     * GET /api/my-company/categories
     */
    public function listCategories(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $categories = ServiceCategory::where('company_id', $company->id)
            ->with('services')
            ->orderBy('name')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }

    /**
     * POST /api/my-company/categories
     */
    public function storeCategory(StoreCategoryRequest $request): ServiceCategoryResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $category = ServiceCategory::create([
            'company_id' => $company->id,
            'name'       => $request->validated('name'),
        ]);

        $category->load('services');

        return (new ServiceCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/my-company/categories/{id}
     */
    public function updateCategory(UpdateCategoryRequest $request, int $id): ServiceCategoryResource|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $category = ServiceCategory::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $category) {
            return $this->notFound('category');
        }

        $category->update($request->validated());
        $category->load('services');

        return new ServiceCategoryResource($category);
    }

    /**
     * DELETE /api/my-company/categories/{id}
     *
     * Deletes the category and all its services (cascade via DB or explicit delete).
     */
    public function destroyCategory(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $category = ServiceCategory::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $category) {
            return $this->notFound('category');
        }

        // Explicitly delete services to fire model events if any are registered
        $category->services()->delete();
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
        ]);
    }

    // =========================================================================
    // Services
    // =========================================================================

    /**
     * POST /api/my-company/services
     */
    public function storeService(StoreServiceRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        // Verify the category belongs to this company
        $categoryBelongs = ServiceCategory::where('id', $request->validated('category_id'))
            ->where('company_id', $company->id)
            ->exists();

        if (! $categoryBelongs) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        $service = Service::create(array_merge(
            $request->validated(),
            ['company_id' => $company->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Service created.',
            'data'    => $this->serviceArray($service),
        ], 201);
    }

    /**
     * PUT /api/my-company/services/{id}
     */
    public function updateService(UpdateServiceRequest $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $service = Service::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $service) {
            return $this->notFound('service');
        }

        // If category_id is being changed, ensure the new category belongs to this company
        if ($request->has('category_id')) {
            $categoryBelongs = ServiceCategory::where('id', $request->validated('category_id'))
                ->where('company_id', $company->id)
                ->exists();

            if (! $categoryBelongs) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found.',
                ], 404);
            }
        }

        $service->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Service updated.',
            'data'    => $this->serviceArray($service->fresh()),
        ]);
    }

    /**
     * DELETE /api/my-company/services/{id}
     */
    public function destroyService(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $service = Service::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $service) {
            return $this->notFound('service');
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted.',
        ]);
    }

    // =========================================================================
    // Employees
    // =========================================================================

    /**
     * GET /api/my-company/employees
     */
    public function listEmployees(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $employees = CompanyUser::where('company_id', $company->id)
            ->with(['user', 'services'])
            ->get();

        return EmployeeResource::collection($employees);
    }

    /**
     * POST /api/my-company/employees/invite
     *
     * Invite an existing user by email. If not found, return a 422 suggesting
     * the owner create an account for them instead.
     */
    public function inviteEmployee(InviteEmployeeRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address. Use the "create employee" endpoint to create a new account.',
            ], 422);
        }

        // Prevent duplicate membership
        $alreadyMember = CompanyUser::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyMember) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a member of your company.',
            ], 422);
        }

        $pivot = CompanyUser::create([
            'company_id'  => $company->id,
            'user_id'     => $user->id,
            'role'        => $request->validated('role', CompanyRole::Employee->value),
            'specialties' => $request->validated('specialties', []),
            'is_active'   => true,
        ]);

        $pivot->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Employee added.',
            'data'    => new EmployeeResource($pivot),
        ], 201);
    }

    /**
     * POST /api/my-company/employees/create
     *
     * Create a new User account and add them as an employee in one transaction.
     */
    public function createEmployee(CreateEmployeeRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $pivot = DB::transaction(function () use ($request, $company): CompanyUser {
            $user = User::create([
                'first_name' => $request->validated('first_name'),
                'last_name'  => $request->validated('last_name'),
                'email'      => $request->validated('email'),
                'phone'      => $request->validated('phone'),
                'password'   => Hash::make($request->validated('password')),
                'role'       => UserRole::User,
            ]);

            return CompanyUser::create([
                'company_id'  => $company->id,
                'user_id'     => $user->id,
                'role'        => $request->validated('role', CompanyRole::Employee->value),
                'specialties' => $request->validated('specialties', []),
                'is_active'   => true,
            ]);
        });

        $pivot->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Employee account created and added to your company.',
            'data'    => new EmployeeResource($pivot),
        ], 201);
    }

    /**
     * PUT /api/my-company/employees/{id}
     *
     * Update role, is_active, or specialties for a company_user row.
     * Cannot demote/change the owner themselves.
     */
    public function updateEmployee(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $pivot = CompanyUser::where('id', $id)
            ->where('company_id', $company->id)
            ->with('user')
            ->first();

        if (! $pivot) {
            return $this->notFound('employee');
        }

        // Owner can modify everything, including their own record

        $validated = $request->validated();

        // Extract service_ids before passing the rest to update()
        $serviceIds = null;
        if (array_key_exists('service_ids', $validated)) {
            $serviceIds = $validated['service_ids'];
            unset($validated['service_ids']);
        }

        if (! empty($validated)) {
            $pivot->update($validated);
        }

        // Sync services when service_ids was explicitly sent (even if empty array)
        if ($serviceIds !== null) {
            // Build sync payload: each service gets an empty pivot (no custom duration)
            $syncPayload = array_fill_keys($serviceIds, ['duration' => null]);
            $pivot->services()->sync($syncPayload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee updated.',
            'data'    => new EmployeeResource($pivot->fresh(['user', 'services'])),
        ]);
    }

    /**
     * DELETE /api/my-company/employees/{id}
     *
     * Remove an employee from the company (deletes the pivot row, not the user).
     */
    public function destroyEmployee(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $pivot = CompanyUser::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $pivot) {
            return $this->notFound('employee');
        }

        // Prevent the owner from removing themselves
        if ($pivot->role === CompanyRole::Owner && $pivot->user_id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove yourself as the company owner.',
            ], 403);
        }

        $pivot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee removed from company.',
        ]);
    }

    // =========================================================================
    // Opening Hours
    // =========================================================================

    /**
     * GET /api/my-company/hours
     */
    public function listHours(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $hours = CompanyOpeningHour::where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->get();

        return OpeningHourResource::collection($hours);
    }

    /**
     * PUT /api/my-company/hours
     *
     * Bulk upsert: each entry in `hours[]` is matched by day_of_week and
     * created-or-updated. Days not present in the payload are left untouched.
     */
    public function updateHours(UpdateOpeningHoursRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        DB::transaction(function () use ($request, $company): void {
            foreach ($request->validated('hours') as $entry) {
                $isClosed = (bool) $entry['is_closed'];

                CompanyOpeningHour::updateOrCreate(
                    [
                        'company_id'  => $company->id,
                        'day_of_week' => $entry['day_of_week'],
                    ],
                    [
                        'open_time'  => $isClosed ? null : ($entry['open_time'] ?? null),
                        'close_time' => $isClosed ? null : ($entry['close_time'] ?? null),
                        'is_closed'  => $isClosed,
                    ]
                );
            }
        });

        $hours = CompanyOpeningHour::where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->get();

        return OpeningHourResource::collection($hours);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function serviceArray(Service $service): array
    {
        return [
            'id'              => (string) $service->id,
            'categoryId'      => (string) $service->category_id,
            'name'            => $service->name,
            'description'     => $service->description,
            'durationMinutes' => (int) $service->duration,
            'price'           => (float) $service->price,
            'isActive'        => (bool) $service->is_active,
            'maxConcurrent'   => $service->max_concurrent !== null ? (int) $service->max_concurrent : null,
        ];
    }

    // =========================================================================
    // Booking Settings
    // =========================================================================

    /**
     * PUT /api/my-company/booking-settings
     */
    public function updateBookingSettings(UpdateBookingSettingsRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $company->update(['booking_mode' => $request->validated('booking_mode')]);

        Cache::forget("company:detail:{$company->id}");

        return response()->json([
            'success'     => true,
            'message'     => 'Booking settings updated.',
            'bookingMode' => $company->fresh()->booking_mode instanceof \BackedEnum
                ? $company->fresh()->booking_mode->value
                : $company->fresh()->booking_mode,
        ]);
    }

    // =========================================================================
    // Company Breaks
    // =========================================================================

    /**
     * GET /api/my-company/breaks
     */
    public function listBreaks(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $breaks = CompanyBreak::where('company_id', $company->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return CompanyBreakResource::collection($breaks);
    }

    /**
     * POST /api/my-company/breaks
     */
    public function storeBreak(StoreCompanyBreakRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $validated = $request->validated();
        $force     = (bool) ($request->input('force', false));

        // Conflict check — refuse if future appointments start during the
        // window unless the caller explicitly confirmed to force. Applies
        // company-wide (companyUserId null) in capacity mode.
        if (! $force) {
            $conflicts = \App\Support\ScheduleConflictChecker::appointmentsInBreakWindow(
                companyId: (int) $company->id,
                companyUserId: null,
                dayOfWeek: isset($validated['day_of_week']) ? (int) $validated['day_of_week'] : null,
                breakStart: (string) $validated['start_time'],
                breakEnd: (string) $validated['end_time'],
            );
            if ($conflicts->isNotEmpty()) {
                return response()->json([
                    'success'   => false,
                    'code'      => 'break_conflict',
                    'message'   => 'Appointments start during this break window.',
                    'conflicts' => \App\Support\ScheduleConflictChecker::toConflictPayload($conflicts),
                ], 409);
            }
        }

        $break = CompanyBreak::create(array_merge(
            $validated,
            ['company_id' => $company->id]
        ));

        Cache::forget("company:detail:{$company->id}");

        return (new CompanyBreakResource($break))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/my-company/breaks/{id}
     */
    public function updateBreak(UpdateCompanyBreakRequest $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $break = CompanyBreak::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $break) {
            return $this->notFound('break');
        }

        $break->update($request->validated());

        Cache::forget("company:detail:{$company->id}");

        return response()->json([
            'success' => true,
            'data'    => new CompanyBreakResource($break->fresh()),
        ]);
    }

    /**
     * DELETE /api/my-company/breaks/{id}
     */
    public function destroyBreak(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $break = CompanyBreak::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $break) {
            return $this->notFound('break');
        }

        $break->delete();

        Cache::forget("company:detail:{$company->id}");

        return response()->json(['success' => true, 'message' => 'Break deleted.']);
    }

    // =========================================================================
    // Company Days Off (capacity mode — salon closed for whole days)
    // =========================================================================

    /**
     * GET /api/my-company/days-off
     *
     * Returns the upcoming + recent company-wide closures. Used by the "Mon
     * salon" settings to list and delete entries.
     */
    public function listDaysOff(): JsonResponse
    {
        $company = $this->resolveOwnedCompany();
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $rows = CompanyDayOff::where('company_id', $company->id)
            ->orderBy('date')
            ->get(['id', 'date', 'reason']);

        $data = $rows->map(fn ($d) => [
            'id'     => (string) $d->id,
            'date'   => $d->date instanceof Carbon
                ? $d->date->toDateString()
                : substr((string) $d->date, 0, 10),
            'reason' => $d->reason,
        ])->all();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * POST /api/my-company/days-off
     *
     * Supports a single date or a range via `until_date`. Refuses with 409
     * and a `conflicts` payload if live appointments exist on any day of
     * the range — the owner must cancel them first.
     */
    public function storeDayOff(Request $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $validated = validator($request->all(), [
            'date'       => ['required', 'date_format:Y-m-d'],
            'until_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'reason'     => ['nullable', 'string', 'max:255'],
        ])->validate();

        $startDate = $validated['date'];
        $endDate   = $validated['until_date'] ?? $startDate;

        $conflicts = \App\Support\ScheduleConflictChecker::appointmentsInDateRange(
            companyId: (int) $company->id,
            companyUserId: null,
            startDate: $startDate,
            endDate: $endDate,
        );
        if ($conflicts->isNotEmpty()) {
            return response()->json([
                'success'   => false,
                'code'      => 'day_off_conflict',
                'message'   => 'Appointments exist on these dates. Cancel or refuse them before adding the day off.',
                'conflicts' => \App\Support\ScheduleConflictChecker::toConflictPayload($conflicts),
            ], 409);
        }

        $existing = CompanyDayOff::where('company_id', $company->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon
                ? $d->toDateString()
                : substr((string) $d, 0, 10))
            ->all();
        $existingSet = array_flip($existing);

        $created = [];
        $cursor  = Carbon::createFromFormat('Y-m-d', $startDate);
        $end     = Carbon::createFromFormat('Y-m-d', $endDate);
        while ($cursor->lte($end)) {
            $dateStr = $cursor->format('Y-m-d');
            if (! isset($existingSet[$dateStr])) {
                $row = CompanyDayOff::create([
                    'company_id' => $company->id,
                    'date'       => $dateStr,
                    'reason'     => $validated['reason'] ?? null,
                ]);
                $created[] = [
                    'id'     => (string) $row->id,
                    'date'   => $row->date instanceof Carbon
                        ? $row->date->toDateString()
                        : substr((string) $row->date, 0, 10),
                    'reason' => $row->reason,
                ];
            }
            $cursor->addDay();
        }

        if (empty($created)) {
            return response()->json([
                'success' => false,
                'message' => 'All days in this range are already marked as off.',
            ], 409);
        }

        Cache::forget("company:detail:{$company->id}");

        return response()->json(['success' => true, 'data' => $created], 201);
    }

    /**
     * DELETE /api/my-company/days-off/{id}
     */
    public function destroyDayOff(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $row = CompanyDayOff::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $row) {
            return $this->notFound('day off');
        }

        $row->delete();
        Cache::forget("company:detail:{$company->id}");

        return response()->json(['success' => true, 'message' => 'Day off deleted.']);
    }

    // =========================================================================
    // Capacity Overrides
    // =========================================================================

    /**
     * GET /api/my-company/capacity-overrides
     */
    public function listCapacityOverrides(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $overrides = CompanyCapacityOverride::where('company_id', $company->id)
            ->orderBy('date')
            ->get();

        return CompanyCapacityOverrideResource::collection($overrides);
    }

    /**
     * POST /api/my-company/capacity-overrides
     */
    public function storeCapacityOverride(StoreCapacityOverrideRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        // Enforce unique constraint gracefully
        $exists = CompanyCapacityOverride::where('company_id', $company->id)
            ->where('date', $request->validated('date'))
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A capacity override already exists for this date.',
                'errors'  => ['date' => ['already_exists']],
            ], 422);
        }

        $override = CompanyCapacityOverride::create(array_merge(
            $request->validated(),
            ['company_id' => $company->id]
        ));

        Cache::forget("company:detail:{$company->id}");
        Cache::forget("company:availability:{$company->id}:{$request->validated('date')}");

        return (new CompanyCapacityOverrideResource($override))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/my-company/capacity-overrides/{id}
     */
    public function updateCapacityOverride(UpdateCapacityOverrideRequest $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $override = CompanyCapacityOverride::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $override) {
            return $this->notFound('capacity override');
        }

        $override->update($request->validated());

        Cache::forget("company:detail:{$company->id}");
        Cache::forget("company:availability:{$company->id}:{$override->date->format('Y-m-d')}");

        return response()->json([
            'success' => true,
            'data'    => new CompanyCapacityOverrideResource($override->fresh()),
        ]);
    }

    /**
     * DELETE /api/my-company/capacity-overrides/{id}
     */
    public function destroyCapacityOverride(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $override = CompanyCapacityOverride::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $override) {
            return $this->notFound('capacity override');
        }

        $dateStr = $override->date->format('Y-m-d');
        $override->delete();

        Cache::forget("company:detail:{$company->id}");
        Cache::forget("company:availability:{$company->id}:{$dateStr}");

        return response()->json(['success' => true, 'message' => 'Capacity override deleted.']);
    }

    // =========================================================================
    // Appointment Approval (Type 2)
    // =========================================================================

    /**
     * PUT /api/my-company/appointments/{id}/status
     *
     * Owner can confirm or reject a pending appointment on a capacity_based company.
     */
    public function updateAppointmentStatus(UpdateAppointmentStatusRequest $request, int $id): JsonResponse
    {
        // Shared by owners (full access) and employees (scoped to their
        // own bookings). The approval flow (pending→confirmed/rejected) is
        // only meaningful in capacity mode, but the transition matrix
        // already limits what's reachable from a given current status —
        // no need for an extra booking-mode gate.
        $access = $this->resolveCompanyAccess();
        if ($access instanceof JsonResponse) {
            return $access;
        }
        $company = $access['company'];
        $employeeScopeId = $access['companyUserId']; // null for owners
        $isOwner = $access['isOwner'];

        $query = Appointment::where('id', $id)
            ->where('company_id', $company->id);
        if ($employeeScopeId !== null) {
            $query->where('company_user_id', $employeeScopeId);
        }
        $appointment = $query->first();

        if (! $appointment) {
            return $this->notFound('appointment');
        }

        $newStatus = AppointmentStatus::from($request->validated('status'));
        $currentStatus = $appointment->status instanceof AppointmentStatus
            ? $appointment->status
            : AppointmentStatus::from((string) $appointment->status);

        // Allowed transitions:
        //   pending   → confirmed | rejected | cancelled   (owner only)
        //   confirmed → cancelled | no_show
        //   rejected  → cancelled                          (owner only)
        // Employees can only cancel/no-show bookings assigned to them —
        // approving or rejecting a pending request belongs to the owner.
        $allowed = match ($currentStatus) {
            AppointmentStatus::Pending => $isOwner
                ? [
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::Rejected,
                    AppointmentStatus::Cancelled,
                ]
                : [AppointmentStatus::Cancelled],
            AppointmentStatus::Confirmed => [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ],
            AppointmentStatus::Rejected => $isOwner
                ? [AppointmentStatus::Cancelled]
                : [],
            default => [],
        };

        if (! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This status transition is not allowed.',
            ], 422);
        }

        // no_show : le RDV doit avoir déjà commencé (starts_at <= now) ET
        // être dans la fenêtre des 24h post-démarrage. Au-delà, l'owner n'a
        // plus le droit de le marquer — on évite la gestion rétroactive et
        // on garde l'UI du planning centrée sur les événements récents.
        if ($newStatus === AppointmentStatus::NoShow) {
            $startsAt = \Carbon\Carbon::parse(
                $appointment->date->format('Y-m-d') . ' ' . $appointment->start_time
            );

            if ($startsAt->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot mark a future appointment as no-show.',
                    'errors'  => ['status' => ['appointment-not-started-yet']],
                ], 422);
            }

            if ($startsAt->diffInHours(now()) >= 24) {
                return response()->json([
                    'success' => false,
                    'message' => 'No-show window has closed (> 24h).',
                    'errors'  => ['status' => ['no-show-window-closed']],
                ], 422);
            }
        }

        // Compose the update payload. The motif is persisted on different
        // columns depending on the target status so the two concepts don't
        // get confused in the owner's history.
        $reason = $request->validated('reason');
        $payload = ['status' => $newStatus];

        if ($newStatus === AppointmentStatus::Rejected) {
            $payload['rejection_reason']     = $reason;
            $payload['rejected_by_owner_at'] = now();
        } elseif ($newStatus === AppointmentStatus::Cancelled
            && $currentStatus !== AppointmentStatus::Rejected) {
            // Owner-initiated cancel on pending/confirmed — keep the motif in
            // the cancel column. For the rejected → cancelled "free slot"
            // flow we keep the original rejection_reason untouched.
            $payload['cancellation_reason'] = $reason;
        }

        $appointment->update($payload);

        $dateStr = $appointment->date instanceof \Carbon\Carbon
            ? $appointment->date->format('Y-m-d')
            : substr((string) $appointment->date, 0, 10);

        Cache::forget("company:availability:{$company->id}:{$dateStr}");

        return response()->json([
            'success' => true,
            'message' => 'Appointment status updated.',
            'status'  => $newStatus->value,
        ]);
    }

    /**
     * POST /api/my-company/walk-in
     *
     * Instantly creates a confirmed walk-in appointment for a capacity_based company.
     * Bypasses the pending/approve workflow. company_user_id is NULL (no per-employee tracking).
     */
    public function storeWalkIn(StoreCompanyWalkInRequest $request): JsonResponse
    {
        // Shared by owners and employees. Scoping of the walk-in depends on
        // company mode and who's calling :
        //   - Capacity mode              → company_user_id = null (no per-pro
        //                                   attribution ; capacity handles it)
        //   - Employee-based + employee  → pinned to caller's pivot
        //   - Employee-based + owner-pro → pinned to caller's pivot too
        //     (the owner of a solo/small salon IS a pro, they need to walk-in
        //     on their own planning — see docs/PLANNING_CONTRACT.md §7)
        $access = $this->resolveCompanyAccess();
        if ($access instanceof JsonResponse) {
            return $access;
        }
        $company = $access['company'];

        $mode = $company->booking_mode instanceof BookingMode
            ? $company->booking_mode
            : BookingMode::from((string) $company->booking_mode);

        // Resolve the caller's pivot id — employees already have it via
        // resolveCompanyAccess, owners need a lookup (they're null-scoped).
        $callerPivotId = $access['companyUserId']
            ?? CompanyUser::where('user_id', auth()->id())
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->value('id');

        // Capacity mode intentionally strips the pivot — the walk-in lives on
        // the company, not a specific pro. Employee-based always pins it.
        $employeeScopeId = $mode === BookingMode::CapacityBased
            ? null
            : $callerPivotId;

        if ($mode !== BookingMode::CapacityBased && $employeeScopeId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot resolve your employee pivot — contact support.',
            ], 403);
        }

        $validated = $request->validated();

        $service = Service::where('id', (int) $validated['service_id'])
            ->where('company_id', $company->id)
            ->first();

        if (! $service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found.',
                'errors'  => ['service_id' => ['Service does not belong to your company.']],
            ], 422);
        }

        $startTime = strlen($validated['start_time']) === 5
            ? $validated['start_time'] . ':00'
            : $validated['start_time'];

        $end      = Carbon::createFromFormat('H:i:s', $startTime)->addMinutes((int) $service->duration);
        $endTime  = $end->format('H:i:s');
        $date     = $validated['date'];

        $appointment = DB::transaction(function () use ($company, $service, $date, $startTime, $endTime, $validated, $employeeScopeId): Appointment {
            // Owner-created walk-ins (capacity mode only, guarded above) bypass
            // capacity limits. Employee walk-ins are pinned to that employee's
            // pivot so they show up on their own schedule.
            return Appointment::create([
                'company_id'         => $company->id,
                'company_user_id'    => $employeeScopeId, // null for capacity owner
                'service_id'         => $service->id,
                'user_id'            => null,
                'date'               => $date,
                'start_time'         => $startTime,
                'end_time'           => $endTime,
                'status'             => AppointmentStatus::Confirmed,
                'is_walk_in'         => true,
                'walk_in_first_name' => $validated['first_name'],
                'walk_in_last_name'  => $validated['last_name'] ?? null,
                'walk_in_phone'      => $validated['phone'] ?? null,
            ]);
        });

        Cache::forget("company:availability:{$company->id}:{$date}");

        $appointment->load(['service', 'companyUser.user', 'user']);

        // Inject viewer context so the returned resource carries correct
        // can.* flags — otherwise the front would see cancel=false on the
        // fresh walk-in until the next full list refresh.
        $request->attributes->set('viewerRole', $access['isOwner'] ? 'owner' : 'employee');
        $request->attributes->set('bookingMode', $mode->value);

        return (new OwnerAppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/my-company/appointments
     *
     * Single-day mode  : ?date=YYYY-MM-DD[&status=…]
     * Date-range mode  : ?start=YYYY-MM-DD&end=YYYY-MM-DD[&status=…]  (max 42 days)
     *
     * Both modes default to confirmed,pending when no status filter is provided.
     */
    public function listAppointments(Request $request): AnonymousResourceCollection|JsonResponse
    {
        // Accept both owners and employees. Owners see the full company
        // calendar; employees see only their own bookings (scoped by their
        // company_user pivot id). This way the planning UI is shared.
        $access = $this->resolveCompanyAccess();
        if ($access instanceof JsonResponse) {
            return $access;
        }
        $company = $access['company'];
        $employeeScopeId = $access['companyUserId']; // null for owners

        // --- Validate query params -----------------------------------------------

        $validated = validator($request->query(), [
            'date'   => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'start'  => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end'    => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'status' => ['sometimes', 'string'],
        ])->validate();

        // --- Determine query mode ------------------------------------------------

        $hasDate  = ! empty($validated['date']);
        $hasRange = ! empty($validated['start']) && ! empty($validated['end']);

        if (! $hasDate && ! $hasRange) {
            return response()->json([
                'success' => false,
                'message' => "Either 'date' or 'start' and 'end' are required.",
                'errors'  => ['date' => ["Either 'date' or 'start' and 'end' are required."]],
            ], 422);
        }

        if ($hasRange) {
            $startDate = Carbon::createFromFormat('Y-m-d', $validated['start']);
            $endDate   = Carbon::createFromFormat('Y-m-d', $validated['end']);

            if ($startDate->diffInDays($endDate) > 42) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 42 days.',
                    'errors'  => ['end' => ['Date range cannot exceed 42 days.']],
                ], 422);
            }
        }

        // --- Parse status filter -------------------------------------------------

        $allowedValues = array_column(AppointmentStatus::cases(), 'value');

        if (isset($validated['status'])) {
            $requestedStatuses = array_map('trim', explode(',', $validated['status']));

            $invalid = array_diff($requestedStatuses, $allowedValues);
            if (! empty($invalid)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value(s): ' . implode(', ', $invalid),
                    'errors'  => ['status' => ['invalid_enum_value']],
                ], 422);
            }

            $statuses = $requestedStatuses;
        } else {
            // Default for the owner planning view: show every status except
            // `rejected`. No-show and cancelled must stay visible so the owner
            // gets a faithful picture of the day (otherwise the timeline
            // appears empty right after marking someone as no-show). Rejected
            // pendings never happened, so they stay out by default.
            $statuses = [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Cancelled->value,
            ];
        }

        // --- Query ---------------------------------------------------------------

        $query = Appointment::where('company_id', $company->id)
            ->whereIn('status', $statuses)
            ->with(['service', 'companyUser.user', 'user']);

        // Employee scope — only their own appointments.
        if ($employeeScopeId !== null) {
            $query->where('company_user_id', $employeeScopeId);
        }

        if ($hasDate) {
            $query->where('date', $validated['date'])
                  ->orderBy('start_time');
        } else {
            $query->whereBetween('date', [$validated['start'], $validated['end']])
                  ->orderBy('date')
                  ->orderBy('start_time');
        }

        $appointments = $query->get();

        $mode = $company->booking_mode instanceof BookingMode
            ? $company->booking_mode->value
            : (string) ($company->booking_mode ?? 'employee_based');

        // Inject viewer context so each item can compute its capabilities.
        // Request attributes propagate to every resource rendered in this call.
        $request->attributes->set('viewerRole', $access['isOwner'] ? 'owner' : 'employee');
        $request->attributes->set('bookingMode', $mode);

        return OwnerAppointmentResource::collection($appointments)
            ->additional(['noShowCounts' => $this->batchNoShowCounts($appointments)]);
    }

    /**
     * GET /api/my-company/planning-settings
     *
     * Returns the UI-driving flags for the shared planning screen. The frontend
     * renders sections/buttons strictly from these flags — no bookingMode or
     * role checks live on the client. See docs/PLANNING_CONTRACT.md.
     *
     *   - showPendingApprovalsPanel : desktop approval panel (capacity owners)
     *   - showNextAppointmentBanner : "next RDV" banner (individual mode only)
     *   - showAllStatuses           : include cancelled/rejected/no_show in the timeline
     *   - allowOverlappingWalkIns   : "+" buttons next to occupied slots (capacity)
     *   - visibleStatuses           : explicit allow-list — the front filters on this
     */
    public function planningSettings(): JsonResponse
    {
        $access = $this->resolveCompanyAccess();
        if ($access instanceof JsonResponse) {
            return $access;
        }

        $company = $access['company'];
        $isOwner = $access['isOwner'];
        $mode    = $company->booking_mode instanceof BookingMode
            ? $company->booking_mode
            : BookingMode::from((string) ($company->booking_mode ?? 'employee_based'));

        $isCapacity = $mode === BookingMode::CapacityBased;

        // Capacity mode shows the full spectrum of statuses (including cancelled
        // / no-show) because the owner needs audit context. Individual mode
        // keeps the timeline focused on active bookings — a cancelled slot is
        // instantly free, the card adds only noise.
        $visibleStatuses = $isCapacity
            ? [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Rejected->value,
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Completed->value,
            ]
            : [
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::NoShow->value, // kept so no-show badges still appear
            ];

        return response()->json([
            'success' => true,
            'data'    => [
                'showPendingApprovalsPanel' => $isOwner && $isCapacity,
                'showNextAppointmentBanner' => ! $isCapacity,
                'showAllStatuses'           => $isCapacity,
                'allowOverlappingWalkIns'   => $isCapacity,
                'visibleStatuses'           => $visibleStatuses,
            ],
        ]);
    }

    /**
     * GET /api/my-company/planning-overlays?start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * Returns the non-appointment overlays for the shared planning view:
     *   - `breaks`  : recurring/one-off break windows relevant to the caller
     *   - `daysOff` : concrete dates within [start, end] to mark as day-off
     *
     * Scoping rules (mirror listAppointments):
     *   - Employee          → their own breaks + days off
     *   - Owner (capacity)  → company-wide breaks + company days off
     *   - Owner (emp-based) → company days off only (per-employee breaks
     *                         are not overlayed on the aggregated owner view —
     *                         owners can check each employee's schedule page)
     */
    public function planningOverlays(Request $request): JsonResponse
    {
        $access = $this->resolveCompanyAccess();
        if ($access instanceof JsonResponse) {
            return $access;
        }
        $company = $access['company'];

        $validated = validator($request->query(), [
            'start' => ['required', 'date_format:Y-m-d'],
            'end'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ])->validate();

        $start = Carbon::createFromFormat('Y-m-d', $validated['start']);
        $end   = Carbon::createFromFormat('Y-m-d', $validated['end']);

        if ($start->diffInDays($end) > 42) {
            return response()->json([
                'success' => false,
                'message' => 'Date range cannot exceed 42 days.',
            ], 422);
        }

        $mode = $company->booking_mode instanceof BookingMode
            ? $company->booking_mode
            : BookingMode::from((string) ($company->booking_mode ?? 'employee_based'));

        $breaks  = [];
        $daysOff = [];

        // Resolve the caller's pivot id. In employee_based mode the owner is
        // often also a pro and has personal breaks/days off stored against
        // their pivot in `employee_breaks` / `employee_days_off`. We always
        // look them up — an owner with no personal schedule just gets nothing.
        $callerPivotId = CompanyUser::where('user_id', auth()->id())
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->value('id');

        // Source 1 — the caller's personal schedule (pivot-scoped).
        // Applies in every mode ; for capacity owners the pivot typically
        // carries nothing, but there's no reason to block the query.
        if ($callerPivotId !== null) {
            $empBreaks = EmployeeBreak::where('company_user_id', $callerPivotId)
                ->get(['id', 'day_of_week', 'start_time', 'end_time', 'label']);

            foreach ($empBreaks as $b) {
                $rawDow = $b->getRawOriginal('day_of_week');
                $breaks[] = [
                    'id'        => (string) $b->id,
                    'dayOfWeek' => $rawDow !== null ? (int) $rawDow : null,
                    'startTime' => substr((string) $b->start_time, 0, 5),
                    'endTime'   => substr((string) $b->end_time, 0, 5),
                    'label'     => $b->label,
                ];
            }

            $empDaysOff = EmployeeDayOff::where('company_user_id', $callerPivotId)
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get(['id', 'date', 'reason']);

            foreach ($empDaysOff as $d) {
                $dateStr = $d->date instanceof Carbon
                    ? $d->date->toDateString()
                    : substr((string) $d->date, 0, 10);
                $daysOff[] = [
                    'id'     => (string) $d->id,
                    'date'   => $dateStr,
                    'reason' => $d->reason,
                ];
            }
        }

        // Source 2 — company-level overlays. Capacity mode always shows them
        // (the "salon is on break" notion). Individual-mode owners also get
        // the company days off so a public holiday closes everyone's planning.
        if ($mode === BookingMode::CapacityBased) {
            $coBreaks = CompanyBreak::where('company_id', $company->id)
                ->get(['id', 'day_of_week', 'start_time', 'end_time', 'label']);

            foreach ($coBreaks as $b) {
                $rawDow = $b->getRawOriginal('day_of_week');
                $breaks[] = [
                    'id'        => (string) $b->id,
                    'dayOfWeek' => $rawDow !== null ? (int) $rawDow : null,
                    'startTime' => substr((string) $b->start_time, 0, 5),
                    'endTime'   => substr((string) $b->end_time, 0, 5),
                    'label'     => $b->label,
                ];
            }
        }

        // Company days off — applies to everyone regardless of mode/role.
        {
            $coDaysOff = CompanyDayOff::where('company_id', $company->id)
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get(['id', 'date', 'reason']);

            foreach ($coDaysOff as $d) {
                $dateStr = $d->date instanceof Carbon
                    ? $d->date->toDateString()
                    : substr((string) $d->date, 0, 10);
                $daysOff[] = [
                    'id'     => (string) $d->id,
                    'date'   => $dateStr,
                    'reason' => $d->reason,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'breaks'  => $breaks,
                'daysOff' => $daysOff,
            ],
        ]);
    }

    /**
     * GET /api/my-company/appointments/pending
     *
     * List pending appointments for the owner's company, ordered by date asc.
     */
    public function pendingAppointments(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $appointments = Appointment::where('company_id', $company->id)
            ->where('status', AppointmentStatus::Pending)
            ->with(['service', 'companyUser.user', 'user'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return OwnerAppointmentResource::collection($appointments)
            ->additional(['noShowCounts' => $this->batchNoShowCounts($appointments)]);
    }

    /**
     * Calcule en une seule requête le nombre de no-show par user_id.
     * Retourne un tableau [user_id => count].
     *
     * @param  \Illuminate\Support\Collection $appointments
     * @return array<int, int>
     */
    private function batchNoShowCounts(\Illuminate\Support\Collection $appointments): array
    {
        $userIds = $appointments
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            return [];
        }

        return Appointment::whereIn('user_id', $userIds)
            ->where('status', AppointmentStatus::NoShow->value)
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->all();
    }
}
