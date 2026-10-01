<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Detail Sesi') }} #{{ $session->id }}
            </h2>
            <a href="{{ route('admin.sesi.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                &larr; Kembali ke Daftar
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <!-- Foto Wajah -->
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Foto Saat Login</h3>
                        @if($session->foto_path)
                            <div class="bg-gray-100 rounded-lg overflow-hidden border">
                                <img src="{{ route('admin.sesi.foto', $session->id) }}" alt="Foto {{ $session->nama }}" class="w-full h-auto object-cover">
                            </div>
                            <p class="text-xs text-gray-500 mt-2 text-center">Diambil dari kamera PC Kiosk</p>
                        @else
                            <div class="bg-gray-100 rounded-lg border h-64 flex items-center justify-center text-gray-400">
                                <span>Tidak ada foto</span>
                            </div>
                        @endif
                    </div>

                    <!-- Detail Data -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-semibold mb-4 border-b pb-2">Informasi Pengunjung & Sesi</h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-gray-500">Nama Lengkap</div>
                                <div class="font-medium text-lg">{{ $session->nama }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">NPM</div>
                                <div class="font-medium text-lg">{{ $session->npm }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Fakultas</div>
                                <div>{{ $session->fakultas }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Program Studi</div>
                                <div>{{ $session->prodi }}</div>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-gray-500">Komputer (PC)</div>
                                <div class="font-semibold text-xl text-blue-600">{{ $session->computer->pc_name }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Hasil Tes Hardware Kiosk</div>
                                <div>
                                    @if($session->hardware_ok)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Normal (Keyboard & Mouse Lolos)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Bermasalah (Ada laporan kerusakan)
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <div class="text-sm text-gray-500">Waktu Mulai</div>
                                <div class="font-medium">{{ $session->jam_mulai->format('l, d F Y H:i:s') }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Waktu Selesai</div>
                                <div class="font-medium">
                                    @if($session->jam_selesai)
                                        {{ $session->jam_selesai->format('l, d F Y H:i:s') }}
                                    @else
                                        <span class="text-green-600">Sedang Berjalan</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Durasi</div>
                                <div class="font-medium">
                                    @if($session->jam_selesai)
                                        {{ $session->jam_mulai->diffForHumans($session->jam_selesai, true) }}
                                    @else
                                        {{ $session->jam_mulai->diffForHumans(now(), true) }} (berjalan)
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
