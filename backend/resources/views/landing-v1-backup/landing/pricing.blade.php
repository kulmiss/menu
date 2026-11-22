<section class="{{ $soft ? 'soft' : '' }}" id="pricing" aria-labelledby="pricing-title">
    <div class="wrap">
        <div class="head reveal"><h2 id="pricing-title">{{ $t('pricing.title') }}</h2><p>{{ $t('pricing.subtitle') }}</p></div>
        <div class="plans">
            @foreach ($plans as $plan)
                <article class="card plan reveal {{ $plan['highlight'] ? 'hl' : '' }}" aria-labelledby="plan-{{ $loop->index }}">
                    @if ($plan['highlight'])<span class="badge">{{ $t('pricing.popular') }}</span>@endif
                    <h3 id="plan-{{ $loop->index }}">{{ $plan['name'] }}</h3>
                    <p class="price">{{ $plan['price'] }} <small>{{ $plan['per'] }}</small></p>
                    @if ($plan['year'])<p class="year">{{ $plan['year'] }}</p>@endif
                    @if ($plan['everything'])<p class="everything">{{ $plan['everything'] }}</p>@endif
                    <ul>
                        @foreach ($plan['limits'] as $line)
                            <li class="muted-li">{!! $icon('check') !!}<span>{{ $line }}</span></li>
                        @endforeach
                        @foreach ($plan['features'] as $line)
                            <li>{!! $icon('check') !!}<span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                    <div class="actions">
                        @if ($signupOpen)
                            {{-- Paid plans start free too; the owner upgrades later (payments are manual). --}}
                            <a class="btn {{ $plan['highlight'] ? 'btn-primary' : 'btn-ghost' }}" href="/signup" data-track="signup">{{ $plan['free'] ? $t('create_account') : $t('pricing.start_paid') }}</a>
                            @if (! $plan['free'] && $plan['contact'])
                                <a class="link" href="{{ $plan['contact']['href'] }}" @if ($plan['contact']['whatsapp']) target="_blank" rel="noopener" @endif>{!! $icon('message') !!} {{ $plan['contact']['label'] }}</a>
                            @endif
                        @elseif ($plan['free'] && $cta)
                            {!! $ctaButton('btn btn-primary', false) !!}
                        @elseif ($plan['contact'])
                            <a class="btn btn-ghost" href="{{ $plan['contact']['href'] }}" @if ($plan['contact']['whatsapp']) target="_blank" rel="noopener" @endif>{!! $icon('message') !!} {{ $plan['contact']['label'] }}</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <p class="note">{{ $t('pricing.upgrade_note') }}</p>
    </div>
</section>
