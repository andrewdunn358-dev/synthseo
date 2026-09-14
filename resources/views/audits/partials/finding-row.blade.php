{{-- One finding, rendered identically wherever it appears - on-page,
     mobile performance, or desktop performance. Pulled into its own
     partial rather than duplicated three times in audits/show, which
     is exactly what had happened when the mobile/desktop split was
     first written inline. --}}
<div class="check-row">
  <span class="check-dot {{ $finding->status }}"></span>
  <div>
    <div class="check-title">{{ $finding->title }}</div>
    @if ($finding->status !== 'pass' && $finding->detail)
      <div class="check-detail">{{ $finding->detail }}</div>
    @endif
    @if ($finding->status !== 'pass' && $finding->value)
      <div class="check-value">{{ $finding->value }}</div>
    @endif
    {{-- The specific offending images, not just "reduce image sizes"
         as a generic sentence - real thumbnails at their real URLs,
         the same way Lighthouse's own web report shows them. See
         PageSpeedService::extractImages. --}}
    @if ($finding->status !== 'pass' && ! empty($finding->images))
      <div class="check-images">
        @foreach ($finding->images as $image)
          <a href="{{ $image['url'] }}" target="_blank" rel="noopener" class="check-image">
            <img src="{{ $image['url'] }}" loading="lazy" alt="">
            @if ($image['wasted_bytes'])
              <span class="check-image-savings">{{ number_format($image['wasted_bytes'] / 1024, 0) }} KiB to save</span>
            @endif
          </a>
        @endforeach
      </div>
    @endif
  </div>
</div>
