<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Uji '.$role,
            'email' => strtolower($role).'.'.uniqid().'@example.test',
            'password' => 'PasswordUji123',
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    public function test_patient_cannot_access_admin_dashboard(): void
    {
        $user = $this->makeUser('pasien');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_network_engineer_cannot_access_admin_dashboard(): void
    {
        $user = $this->makeUser('network_engineer');

        $this->actingAs($user)->get('/admin/dashboard')->assertStatus(403);
    }

    public function test_admin_dashboard_works_on_empty_database(): void
    {
        // Database kosong: dashboard harus tetap 200 dengan empty-state
        // (angka nol hanya bila query berhasil; bukan error).
        $user = $this->makeUser('admin');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Total Pasien Terdaftar');
    }

    public function test_inactive_account_is_logged_out(): void
    {
        $user = $this->makeUser('admin');
        $user->update(['is_active' => false]);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_session_pages_are_removed(): void
    {
        // Sesi pemeriksaan kini ranah dokter: halaman admin dihapus.
        $user = $this->makeUser('admin');

        $this->actingAs($user)->get('/admin/sessions')->assertStatus(404);
        $this->actingAs($user)->get('/admin/ecg-history')->assertStatus(404);
    }

    public function test_admin_notes_page_works_on_empty_database(): void
    {
        $user = $this->makeUser('admin');

        $response = $this->actingAs($user)->get('/admin/notes');

        $response->assertStatus(200);
        $response->assertSee('Catatan Pemeriksaan');
    }

    public function test_patient_cannot_access_admin_notes(): void
    {
        $user = $this->makeUser('pasien');

        $this->actingAs($user)->get('/admin/notes')->assertStatus(403);
    }

    public function test_patient_notes_page_works_without_profile(): void
    {
        // Akun pasien tanpa profil pasien: halaman tetap 200 dengan empty-state.
        $user = $this->makeUser('pasien');

        $response = $this->actingAs($user)->get('/patient/notes');

        $response->assertStatus(200);
        $response->assertSee('Catatan Dokter');
    }
}
