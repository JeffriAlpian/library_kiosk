<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Status Komputer Kiosk') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b">
                                    <th class="p-3">PC</th>
                                    <th class="p-3">MAC Address</th>
                                    <th class="p-3">Status Saat Ini</th>
                                    <th class="p-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($computers as $komputer)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3 font-semibold">{{ $komputer->pc_name }}</td>
                                    <td class="p-3 font-mono text-sm text-gray-600">{{ $komputer->mac_address }}</td>
                                    <td class="p-3">
                                        @if($komputer->status === \App\Models\Computer::AVAILABLE)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Tersedia
                                            </span>
                                        @elseif($komputer->status === \App\Models\Computer::IN_USE)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Sedang Dipakai
                                            </span>
                                        @elseif($komputer->status === \App\Models\Computer::MAINTENANCE)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Maintenance
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        @if($komputer->status === \App\Models\Computer::IN_USE)
                                            <span class="text-gray-400 text-sm italic">Tidak bisa diubah saat dipakai</span>
                                        @else
                                            <form action="{{ route('admin.komputer.status', $komputer->id) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                
                                                @if($komputer->status === \App\Models\Computer::MAINTENANCE)
                                                    <input type="hidden" name="status" value="{{ \App\Models\Computer::AVAILABLE }}">
                                                    <button type="submit" class="text-green-600 hover:text-green-900 text-sm font-medium underline" onclick="return confirm('Kembalikan PC ini agar bisa dipakai pengunjung lagi?')">
                                                        Tandai Tersedia
                                                    </button>
                                                @else
                                                    <input type="hidden" name="status" value="{{ \App\Models\Computer::MAINTENANCE }}">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium underline" onclick="return confirm('Tandai PC ini sebagai maintenance? Pengunjung tidak akan bisa login ke PC ini.')">
                                                        Tandai Maintenance
                                                    </button>
                                                @endif
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
