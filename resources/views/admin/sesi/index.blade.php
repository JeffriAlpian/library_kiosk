<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Riwayat Sesi Pengunjung') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filter Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    <form method="GET" action="{{ route('admin.sesi.index') }}" class="flex flex-col md:flex-row gap-4 items-end">
                        <div class="flex-1 w-full">
                            <label class="block text-sm font-medium text-gray-700">Komputer (PC)</label>
                            <select name="pc_name" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">Semua PC</option>
                                @foreach($computers as $pc)
                                    <option value="{{ $pc }}" {{ request('pc_name') == $pc ? 'selected' : '' }}>{{ $pc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-sm font-medium text-gray-700">Pencarian (Nama/NPM)</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari..." class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-sm font-medium text-gray-700">Tanggal Sesi</label>
                            <input type="date" name="tanggal" value="{{ request('tanggal') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        </div>
                        <div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Filter
                            </button>
                            <a href="{{ route('admin.sesi.index') }}" class="ml-2 inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-50 border-b">
                                    <th class="p-3">PC</th>
                                    <th class="p-3">Pengunjung</th>
                                    <th class="p-3">Mulai</th>
                                    <th class="p-3">Selesai</th>
                                    <th class="p-3">HW</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sessions as $sesi)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-semibold">{{ $sesi->computer->pc_name }}</td>
                                    <td class="p-3">
                                        <div class="font-medium">{{ $sesi->nama }}</div>
                                        <div class="text-xs text-gray-500">{{ $sesi->npm }} - {{ $sesi->prodi }}</div>
                                    </td>
                                    <td class="p-3">{{ $sesi->jam_mulai->format('d/m/Y H:i') }}</td>
                                    <td class="p-3">
                                        @if($sesi->jam_selesai)
                                            {{ $sesi->jam_selesai->format('H:i') }}
                                        @else
                                            <span class="text-green-600 font-semibold">-</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($sesi->hardware_ok)
                                            <span class="text-green-600">✓</span>
                                        @else
                                            <span class="text-red-600 font-bold" title="Hardware bermasalah saat login">X</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        <a href="{{ route('admin.sesi.show', $sesi->id) }}" class="text-indigo-600 hover:text-indigo-900">Detail</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
                                        Tidak ada data sesi yang ditemukan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        {{ $sessions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
