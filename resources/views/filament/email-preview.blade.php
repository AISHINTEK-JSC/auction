{{-- An email shown inside the admin panel. The sandboxed frame keeps its styles (and anything else) to itself. --}}
<div style="font-size: 0.875rem;">
    <p style="margin: 0 0 0.75rem;"><span style="opacity: 0.7;">Tiêu đề:</span> <strong>{{ $subject }}</strong></p>
    <iframe
        title="Xem trước email"
        sandbox=""
        srcdoc="{{ $html }}"
        style="display: block; width: 100%; height: 34rem; border: 1px solid rgb(128 128 128 / 0.3); border-radius: 0.5rem; background: #f4f4f5;"
    ></iframe>
</div>
