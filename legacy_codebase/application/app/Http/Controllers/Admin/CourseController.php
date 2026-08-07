<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(): Response
    {
        $courses = Course::query()
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Courses/Create');
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $course = Course::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'is_active' => true,
        ]);

        app(AuditService::class)->log('course.created', Course::class, $course->id, [], [
            'name' => $validated['name'],
            'code' => $validated['code'],
        ]);

        return redirect()->route('admin.courses.index')->with('success', 'Course created.');
    }

    public function edit(Course $course): Response
    {
        return Inertia::render('Admin/Courses/Edit', [
            'course' => $course,
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        app(AuditService::class)->log('course.updated', Course::class, $course->id, [], $request->validated());

        return redirect()->route('admin.courses.index')->with('success', 'Course updated.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        app(AuditService::class)->log('course.deleted', Course::class, $course->id, ['name' => $course->name]);

        $course->delete(); // soft delete

        return redirect()->route('admin.courses.index')->with('success', 'Course deleted.');
    }

    public function activate(Course $course): RedirectResponse
    {
        $course->update(['is_active' => true]);

        app(AuditService::class)->log('course.activated', Course::class, $course->id);

        return redirect()->route('admin.courses.index')->with('success', 'Course activated.');
    }

    public function deactivate(Course $course): RedirectResponse
    {
        $course->update(['is_active' => false]);

        app(AuditService::class)->log('course.deactivated', Course::class, $course->id);

        return redirect()->route('admin.courses.index')->with('success', 'Course deactivated.');
    }

    public function restore(Course $course): RedirectResponse
    {
        $course->restore();

        return redirect()->route('admin.courses.index')->with('success', 'Course restored.');
    }
}
