<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdminDanPerusahaan;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDanPerusahaan\StoreLowonganKerjaRequest;
use App\Models\{JenisPekerjaan, LowonganKerja, MitraPerusahaan, PendaftaranLowongan, PenilaianSeleksi, TahapanSeleksi};
use App\Traits\HasMainRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\ItemNotFoundException;

final class LowonganKerjaController extends Controller {
    use HasMainRoute;

    /**
     * Melakukan set untuk route utama dari controller ini
     */
    public function __construct() {
        $this->setMainRoute('lowongankerja.index');
    }

    /**
     * Mendapatkan semua data kantor dengan format JSON
     *
     * @param MitraPerusahaan $mitra
     * @return JsonResponse
     */
    public function getKantorJSONFormat(MitraPerusahaan $mitra): JsonResponse {
        return new JsonResponse($mitra->kantor);
    }

    /**
     * Mendapatkan semua data lowongan kerja
     *
     * @return View
     */
    public function getAllJobVacanciesData(): View {
        $lowongan = null;
        $lowonganNeedApprove = null;
        $pendaftaranLowongan = null;
        $data = [];

        if (Gate::check('admin')) {
            $pendaftaranLowongan = PendaftaranLowongan::count();
            $lowonganNeedApprove = LowonganKerja::needApproved()->count();
            $lowongan = LowonganKerja::with('perusahaan')
                ->approvedAndActive()
                ->latest()
                ->paginate(10)
                ->withQueryString();

            $data['lowongan'] = $lowongan;
            $data['pendaftaranLowongan'] = $pendaftaranLowongan;
            $data['lowonganNeedApprove'] = $lowonganNeedApprove;
        } else if (Gate::check('perusahaan')) {
            $lowonganApproveAndActive = Auth::user()
                ->perusahaan
                ->lowongan()
                ->approvedAndActive()
                ->latest()
                ->paginate(10)
                ->withQueryString();

            $lowonganNotYetApprovedAndNotYetActive = Auth::user()
                ->perusahaan
                ->lowongan()
                ->notYetApprovedAndNotYetActive()
                ->latest()
                ->paginate(10)
                ->withQueryString();

            $data['lowonganApproveAndActive'] = $lowonganApproveAndActive;
            $data['lowonganNotYetApprovedAndNotYetActive'] = $lowonganNotYetApprovedAndNotYetActive;
        }

        return view('lowongankerja.index', $data);
    }

    /**
     * Menampilkan view untuk menambah data lowongan kerja
     *
     * @return View
     */
    public function createOneJobVacancyData(): View {
        $perusahaan = null;
        $jenisPekerjaan = JenisPekerjaan::all();

        if (Gate::check('admin')) $perusahaan = MitraPerusahaan::all();

        return view('lowongankerja.tambah', compact('perusahaan', 'jenisPekerjaan'));
    }

    /**
     * Memproses, validasi dan melakukan insert data lowongan kerja ke dalam table lowongan_kerja
     *
     * @param StoreLowonganKerjaRequest $request
     * @return RedirectResponse
     */
    public function storeOneJobVacancyData(StoreLowonganKerjaRequest $request): RedirectResponse {
        try {
            $validatedData = $request->validatedData();
            $validatedData['slug'] = Helper::generateUniqueSlug($validatedData['judul_lowongan']);

            if ($request->hasFile('banner')) {
                $validatedData['banner'] = $request->file('banner')->store('lowongan');
            }

            if (Gate::check('perusahaan')) {
                Auth::user()->perusahaan->lowongan()->create($validatedData);
            } else if (Gate::check('admin')) {
                $validatedData['id_perusahaan'] = collect($request->only('id_perusahaan'))->first();
                $validatedData['is_approve'] = true;
                $validatedData['active'] = true;

                LowonganKerja::create($validatedData);
            }

            notify()->success('Berhasil menambahkan data Lowongan baru.', 'Notifikasi');

            return $this->redirectToMainRoute();
        } catch (\Exception $e) {
            notify()->error($e->getMessage(), 'Notifikasi');
            return $this->redirectToMainRoute();
        }
    }

    /**
     * Menampilkan view untuk melihat data lowongan kerja yang membutuhkan verifikasi admin
     *
     * @return View
     */
    public function jobVacanciesThatRequireApproval(): View {
        $lowongan = LowonganKerja::with(['perusahaan'])
            ->needApproved()
            ->hasTahapan()
            ->latest()
            ->get();

        return view('lowongankerja.jobVacanciesThatRequireApproval', compact('lowongan'));
    }

    /**
     * Proses menyetujui verifikasi lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return RedirectResponse
     */
    public function approveJobVacancies(LowonganKerja $lowonganKerja): RedirectResponse {
        $lowonganKerja->update([
            'is_approve' => true,
            'active' => true
        ]);

        notify()->success("Berhasil mensetujui lowongan {$lowonganKerja->judul_lowongan}", 'Notifikasi');

        return to_route('lowongankerja.index');
    }

    /**
     * Proses menolak verifikasi lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return RedirectResponse
     */
    public function rejectJobVacancies(LowonganKerja $lowonganKerja): RedirectResponse {
        $lowonganKerja->update([
            'is_approve' => false,
            'active' => false
        ]);

        notify()->success("Berhasil menolak lowongan {$lowonganKerja->judul_lowongan}", 'Notifikasi');

        return to_route('lowongankerja.index');
    }

    /**
     * Menampilkan view untuk melihat detail data lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return View|RedirectResponse
     */
    public function getDetailOneJobVacancyData(LowonganKerja $lowonganKerja): View|RedirectResponse {
        return view('lowongankerja.detail', compact('lowonganKerja'));
    }

    /**
     * Menampilkan view untuk melihat list pendaftar yang melamar di suatu lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return View
     */
    public function seeApplicants(LowonganKerja $lowonganKerja): View {
        $pendaftaranLowongan = $lowonganKerja
            ->pendaftaran_lowongan()
            ->hasVerified()
            ->paginate(10);

        return view('lowongankerja.see-applicants', compact('pendaftaranLowongan', 'lowonganKerja'));
    }

    /**
     * Menampilkan view untuk melihat list tahapan yang terdapat pada lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return View
     */
    public function seeStages(LowonganKerja $lowonganKerja): View {
        return view('lowongankerja.see-stages', compact('lowonganKerja'));
    }

    /**
     * Menampilkan view untuk memberikan penilaian seleksi yang dilakukan oleh mitra
     *
     * @param LowonganKerja $lowonganKerja
     * @param TahapanSeleksi $tahapanSeleksi
     * @return View
     */
    public function applicantSelection(LowonganKerja $lowonganKerja, TahapanSeleksi $tahapanSeleksi): View {
        $pendaftaranLowongan = [];

        if ($tahapanSeleksi->urutan_tahapan_ke === 1) {
            $pendaftaranLowongan = $lowonganKerja
                ->pendaftaran_lowongan()
                ->hasVerified()
                ->paginate(10);
        } else {
            $pendaftaranLowongan = $lowonganKerja
                ->pendaftaran_lowongan()
                ->isLanjut()
                ->hasVerified()
                ->paginate(10);
        }

        return view('lowongankerja.applicant-selection', compact('lowonganKerja', 'tahapanSeleksi', 'pendaftaranLowongan'));
    }

    /**
     * Memproses penilaian seleksi yang dilakukan oleh mitra
     *
     * @param Request $request
     * @param LowonganKerja $lowonganKerja
     * @param TahapanSeleksi $tahapanSeleksi
     * @return RedirectResponse
     */
    public function storeApplicantSelection(
        Request $request,
        LowonganKerja $lowonganKerja,
        TahapanSeleksi $tahapanSeleksi
    ): RedirectResponse {
        $request->validate([
            'nilai' => ['required', 'min:1', 'max:100']
        ], [
            'nilai.required' => 'Nilai tidak boleh kosong.',
            'nilai.min' => 'Nilai tidak boleh kurang dari 1.',
            'nilai.max' => 'Nilai tidak boleh lebih dari 100.',
        ]);

        $data = $request->only(['id_pendaftaran', 'id_pelamar', 'nilai', 'keterangan', 'id_penilaian_seleksi']);

        for ($i = 0; $i < count($data['id_pendaftaran']); $i++) {
            if (empty($data['keterangan'][$i])) {
                PendaftaranLowongan::firstWhere('id_pendaftaran', $data['id_pendaftaran'][$i])
                    ->update(['status_seleksi' => 'Tidak']);
            }

            PenilaianSeleksi::updateOrCreate([
                'id_penilaian_seleksi' => $data['id_penilaian_seleksi'][$i]
            ], [
                'id_pelamar' => $data['id_pelamar'][$i],
                'id_tahapan' => $tahapanSeleksi->id_tahapan,
                'id_pendaftaran' => $data['id_pendaftaran'][$i],
                'nilai' => $data['nilai'][$i],
                'keterangan' => !empty($data['keterangan'][$i]) ? 'Lulus' : 'Gagal',
                'is_lanjut' => !empty($data['keterangan'][$i]) ? 'Ya' : 'Tidak'
            ]);

            if (is_null(
                $tahapanSeleksi
                    ->where('id_lowongan', $tahapanSeleksi->lowongan->id_lowongan)
                    ->where('urutan_tahapan_ke', $tahapanSeleksi->urutan_tahapan_ke + 1)->first()
            )) {
                if (!empty($data['keterangan'][$i])) {
                    PendaftaranLowongan::firstWhere('id_pendaftaran', $data['id_pendaftaran'][$i])
                        ->update(['status_seleksi' => 'Lulus']);
                }
            }
        }

        $tahapanSeleksi->update([
            'status' => 'Menunggu Persetujuan Admin'
        ]);

        notify()->success("Berhasil memberikan penilaian kepada pendaftar pada seleksi {$tahapanSeleksi->judul_tahapan}", "Notifikasi");

        return to_route('lowongankerja.see-stages', $lowonganKerja->slug);
    }

    /**
     * Menampilkan view form update untuk data lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return View|RedirectResponse
     */
    public function editOneJobVacancyData(LowonganKerja $lowonganKerja): View|RedirectResponse {
        try {
            $lowongan = null;
            $perusahaan = null;
            $jenisPekerjaan = JenisPekerjaan::all();

            if (Gate::check('perusahaan')) {
                $lowongan = Auth::user()->perusahaan->lowongan->firstWhere('id_lowongan', $lowonganKerja->id_lowongan);
            } else if (Gate::check('admin')) {
                $lowongan = $lowonganKerja;
                $perusahaan = MitraPerusahaan::all();
            }

            return view('lowongankerja.sunting', compact('lowongan', 'perusahaan', 'jenisPekerjaan'));
        } catch (ItemNotFoundException) {
            return $this->redirectToMainRoute()->with('error', 'Data lowongan kerja tidak ditemukan');
        }
    }

    /**
     * Memproses update data lowongan kerja
     *
     * @param StoreLowonganKerjaRequest $request
     * @param LowonganKerja $lowonganKerja
     * @return RedirectResponse
     */
    public function updateOneJobVacancyData(
        StoreLowonganKerjaRequest $request,
        LowonganKerja $lowonganKerja
    ): RedirectResponse {
        try {
            $validatedData = $request->validatedData();

            if ($validatedData['judul_lowongan'] !== $lowonganKerja->judul_lowongan) {
                $validatedData['slug'] = Helper::generateUniqueSlug($validatedData['judul_lowongan']);
            }

            if ($request->hasFile('banner')) {
                $validatedData['banner'] = $request->file('banner')->store('lowongan');
                Helper::deleteFileIfExistsInStorageFolder($lowonganKerja->banner);
            }

            if (Gate::check('perusahaan')) {
                Auth::user()->perusahaan->lowongan()->firstWhere('slug', $lowonganKerja->slug)->update($validatedData);
            } else if (Gate::check('admin')) {
                $validatedData['id_perusahaan'] = $lowonganKerja->perusahaan->id_perusahaan;
                $lowonganKerja->update($validatedData);
            }

            notify()->success('Berhasil memperbarui data Lowongan.', 'Notifikasi');

            return $this->redirectToMainRoute();
        } catch (ItemNotFoundException) {
            notify()->error('Data lowongan kerja tidak ditemukan', 'Notifikasi');
            return $this->redirectToMainRoute();
        }
    }

    /**
     * Menonaktifkan data lowongan kerja
     *
     * @param LowonganKerja $lowonganKerja
     * @return RedirectResponse
     */
    public function deactiveOneJobVacancy(LowonganKerja $lowonganKerja): RedirectResponse {
        try {
            $lowonganKerja->update(['active' => false]);
            notify()->success('Berhasil menonaktifkan lowongan', 'Notifikasi');
            return back();
        } catch (\Exception $e) {
            notify()->error($e->getMessage(), 'Notifikasi');
            return back();
        }
    }
}
