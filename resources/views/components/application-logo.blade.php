{{--
    The app's own mark.

    This was Laravel's logo, straight from the scaffold. It sat on the login page
    and would have sat at the top of the landing page — presenting somebody
    else's trademark as this product's identity, on the one page whose whole job
    is to say what this product is.

    Drawn with currentColor so it takes the colour of whatever it sits in, on a
    32-unit grid so it stays crisp at the 28px it is usually rendered at.
--}}
<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" fill="none" role="img"
     aria-label="{{ __('app.brand') }}" {{ $attributes }}>
    {{-- Three rising bars: the analysis. --}}
    <rect x="4" y="17" width="6" height="9" rx="2" fill="currentColor" opacity="0.4" />
    <rect x="13" y="11" width="6" height="15" rx="2" fill="currentColor" opacity="0.7" />
    <rect x="22" y="5" width="6" height="21" rx="2" fill="currentColor" />

    {{-- The bar they rest on: the training. --}}
    <rect x="2" y="28" width="28" height="2.5" rx="1.25" fill="currentColor" />
</svg>
