<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard Kiosk') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm">Total Komputer</div>
                    <div class="text-3xl font-bold">{{ $stats['total_komputer'] }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm">Sedang Dipakai</div>
                    <div class="text-3xl font-bold text-blue-600">{{ $stats['komputer_aktif'] }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm">Sesi Hari Ini</div>
                    <div class="text-3xl font-bold text-green-600">{{ $stats['sesi_hari_ini'] }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-500 text-sm">Dalam Perbaikan (Maintenance)</div>
                    <div class="text-3xl font-bold text-red-600">{{ $stats['komputer_maintenance'] }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4">Sesi Terbaru</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b">
                                    <th class="p-3">PC</th>
                                    <th class="p-3">Pengunjung</th>
                                    <th class="p-3">Waktu Mulai</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sesi_terbaru as $sesi)
                                <tr class="border-b">
                                    <td class="p-3 font-semibold">{{ $sesi->computer->pc_name }}</td>
                                    <td class="p-3">
                                        {{ $sesi->nama }}<br>
                                        <span class="text-xs text-gray-500">{{ $sesi->npm }}</span>
                                    </td>
                                    <td class="p-3">{{ $sesi->jam_mulai->format('d/m/Y H:i') }}</td>
                                    <td class="p-3">
                                        @if($sesi->jam_selesai)
                                            <span class="px-2 py-1 text-xs rounded bg-gray-100">Selesai ({{ $sesi->jam_selesai->format('H:i') }})</span>
                                        @else
                                            <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">Sedang Berjalan</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="p-3 text-center text-gray-500">Belum ada sesi hari ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
