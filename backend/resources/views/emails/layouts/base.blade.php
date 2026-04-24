{{--
    Termini Im — base email layout
    --------------------------------------------------------------------
    Mirrors the Flutter app design system (see hairspot_mobile/lib/core/theme/).

    Sections a child template must define:
      - @section('preheader') : hidden inbox-preview text
      - @section('eyebrow')   : small uppercase overline above the heading
      - @section('heading')   : main title (Fraunces)
      - @section('content')   : body HTML
      - @section('accent')    : accent color for the code block border
                                (defaults to primary bourgogne)

    Palette (from lib/core/theme/app_colors.dart):
      primary / bourgogne   #7A2232    primaryLight  #9E3D4F
      secondary / gold      #C89B47    secondaryDark #A07A2C
      background / sand     #F7F2EA    surface / ivory #FCF7EE
      ink #171311  textSecondary #332C29  textHint #716059
      divider #E8DCC8  border #D9CAB3  ivoryAlt #EFE6D5

    Typography (Google Fonts):
      Fraunces          - serif headings
      Instrument Sans   - body, captions, buttons
      Instrument Serif  - italic emphasis (the "im" wordmark)
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', 'Termini Im')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500&family=Instrument+Sans:wght@400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
        img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; }

        @media only screen and (max-width: 600px) {
            .container { width:100% !important; }
            .px-gutter { padding-left:28px !important; padding-right:28px !important; }
            .px-header { padding-left:28px !important; padding-right:28px !important; }
            .code-cell { font-size:32px !important; letter-spacing:10px !important; }
            .hero-title { font-size:24px !important; line-height:30px !important; }
            .wordmark { font-size:36px !important; }
            .wordmark-im { font-size:38px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#F7F2EA; font-family:'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color:#171311;">

    {{-- Hidden inbox-preview text --}}
    <div style="display:none; font-size:1px; color:#F7F2EA; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        @yield('preheader', '')
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F7F2EA;">
        <tr>
            <td align="center" style="padding:40px 16px 32px 16px;">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="container"
                       style="width:600px; max-width:100%; background:#FCF7EE; border:1px solid #D9CAB3; border-radius:12px; overflow:hidden;">

                    {{-- ============ HEADER (logo wordmark, light) ============ --}}
                    <tr>
                        <td class="px-header" align="center" style="padding:48px 40px 36px 40px; background:#FCF7EE; border-bottom:1px solid #E8DCC8;">
                            {{-- Signature dot above the wordmark --}}
                            <div style="width:8px; height:8px; background:#7A2232; border-radius:50%; line-height:1px; font-size:0; margin:0 auto 12px auto;">&nbsp;</div>

                            {{-- Wordmark: "Termini" (Fraunces ink) + "im." (Instrument Serif italic bourgogne) --}}
                            <div style="line-height:1; white-space:nowrap; letter-spacing:-1.5px;">
                                <span class="wordmark" style="font-family:'Fraunces', 'Times New Roman', Georgia, serif; font-size:44px; font-weight:400; color:#171311; letter-spacing:-1.5px;">Termini&nbsp;</span><span class="wordmark-im" style="font-family:'Instrument Serif', 'Times New Roman', Georgia, serif; font-style:italic; font-size:46px; font-weight:400; color:#7A2232; letter-spacing:-0.5px;">im.</span>
                            </div>

                            {{-- Divider + tagline --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;">
                                <tr>
                                    <td style="width:40px; border-top:1px solid #D9CAB3; line-height:1px; font-size:0;">&nbsp;</td>
                                    <td style="padding:0 14px; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; color:#716059; letter-spacing:2.8px; text-transform:uppercase; white-space:nowrap;">
                                        {{ __('emails.brand_tagline') }}
                                    </td>
                                    <td style="width:40px; border-top:1px solid #D9CAB3; line-height:1px; font-size:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ BODY ============ --}}
                    <tr>
                        <td class="px-gutter" style="padding:44px 56px 8px 56px;">

                            @hasSection('eyebrow')
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2.8px; text-transform:uppercase; color:#7A2232; margin-bottom:16px;">
                                @yield('eyebrow')
                            </div>
                            @endif

                            @hasSection('heading')
                            <h1 class="hero-title" style="margin:0 0 24px 0; font-family:'Fraunces', 'Times New Roman', Georgia, serif; font-size:32px; line-height:38px; font-weight:400; color:#171311; letter-spacing:-0.8px;">
                                @yield('heading')
                            </h1>
                            @endif

                            @yield('content')

                        </td>
                    </tr>

                    {{-- ============ DIVIDER ============ --}}
                    <tr>
                        <td class="px-gutter" style="padding:16px 56px 0 56px;">
                            <div style="height:1px; background:#E8DCC8; line-height:1px; font-size:0;">&nbsp;</div>
                        </td>
                    </tr>

                    {{-- ============ FOOTER ============ --}}
                    <tr>
                        <td class="px-gutter" align="center" style="padding:24px 56px 36px 56px;">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:12px; line-height:18px; color:#716059; margin-bottom:10px;">
                                {{ __('emails.footer_legal') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; letter-spacing:2.8px; text-transform:uppercase; color:#A07A2C;">
                                {{ __('emails.footer_brand') }}
                            </div>
                        </td>
                    </tr>

                </table>

                <div style="max-width:600px; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:11px; line-height:16px; color:#716059; padding:18px 24px 0 24px; text-align:center;">
                    &copy; {{ date('Y') }} Termini <span style="font-family:'Instrument Serif', Georgia, serif; font-style:italic; color:#7A2232;">im.</span>
                </div>

            </td>
        </tr>
    </table>
</body>
</html>
