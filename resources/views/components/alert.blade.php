<div {{ $attributes->class([
    'p-4 rounded-xl w-full text-center',
    'bg-blue-100 text-blue-800' => $type==='info',
    'bg-orange-100 text-orange-800' => $type==='warning',
    'bg-red-100 text-red-800' => $type==='danger',
    'bg-green-100 text-green-800' => $type==='success',
]) }}>
    {{ $slot }}
</div>
