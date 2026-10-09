<div class="overflow-x-auto">
    @if ($entries->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có ai đăng ký tham gia.</p>
    @else
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-2 py-2 font-medium">Mã số</th>
                    <th scope="col" class="px-2 py-2 font-medium">Thành viên</th>
                    <th scope="col" class="px-2 py-2 font-medium">Email</th>
                    <th scope="col" class="px-2 py-2 font-medium">Đăng ký lúc</th>
                    <th scope="col" class="px-2 py-2 font-medium">Kết quả</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($entries as $entry)
                    <tr>
                        <td class="px-2 py-2 font-mono">{{ $entry->user->member_code }}</td>
                        <td class="px-2 py-2">{{ $entry->user->name }}</td>
                        <td class="px-2 py-2">{{ $entry->user->email }}</td>
                        <td class="px-2 py-2">{{ $entry->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-2 py-2">{{ $entry->is_winner ? 'Trúng thưởng' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
