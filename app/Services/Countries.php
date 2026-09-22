<?php

namespace App\Services;

/**
 * ISO 3166-1 alpha-2 lookups for the analytics screens.
 *
 * Flags are derived arithmetically from the country code (regional indicator
 * symbols), so only the names need to be stored.
 */
class Countries
{
    /** @var array<string, string> */
    private const NAMES = [
        'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AL' => 'Albania', 'AM' => 'Armenia',
        'AO' => 'Angola', 'AR' => 'Argentina', 'AT' => 'Austria', 'AU' => 'Australia',
        'AZ' => 'Azerbaijan', 'BA' => 'Bosnia and Herzegovina', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
        'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Bahrain', 'BI' => 'Burundi',
        'BJ' => 'Benin', 'BN' => 'Brunei', 'BO' => 'Bolivia', 'BR' => 'Brazil',
        'BW' => 'Botswana', 'BY' => 'Belarus', 'CA' => 'Canada', 'CD' => 'DR Congo',
        'CG' => 'Congo', 'CH' => 'Switzerland', 'CI' => "Côte d'Ivoire", 'CL' => 'Chile',
        'CM' => 'Cameroon', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica',
        'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DE' => 'Germany',
        'DK' => 'Denmark', 'DO' => 'Dominican Republic', 'DZ' => 'Algeria', 'EC' => 'Ecuador',
        'EE' => 'Estonia', 'EG' => 'Egypt', 'ES' => 'Spain', 'ET' => 'Ethiopia',
        'FI' => 'Finland', 'FJ' => 'Fiji', 'FR' => 'France', 'GB' => 'United Kingdom',
        'GE' => 'Georgia', 'GH' => 'Ghana', 'GR' => 'Greece', 'GT' => 'Guatemala',
        'HK' => 'Hong Kong', 'HN' => 'Honduras', 'HR' => 'Croatia', 'HU' => 'Hungary',
        'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel', 'IN' => 'India',
        'IQ' => 'Iraq', 'IR' => 'Iran', 'IS' => 'Iceland', 'IT' => 'Italy',
        'JM' => 'Jamaica', 'JO' => 'Jordan', 'JP' => 'Japan', 'KE' => 'Kenya',
        'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia', 'KR' => 'South Korea', 'KW' => 'Kuwait',
        'KZ' => 'Kazakhstan', 'LA' => 'Laos', 'LB' => 'Lebanon', 'LK' => 'Sri Lanka',
        'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'LY' => 'Libya',
        'MA' => 'Morocco', 'MD' => 'Moldova', 'ME' => 'Montenegro', 'MG' => 'Madagascar',
        'MK' => 'North Macedonia', 'ML' => 'Mali', 'MM' => 'Myanmar', 'MN' => 'Mongolia',
        'MT' => 'Malta', 'MU' => 'Mauritius', 'MV' => 'Maldives', 'MW' => 'Malawi',
        'MX' => 'Mexico', 'MY' => 'Malaysia', 'MZ' => 'Mozambique', 'NA' => 'Namibia',
        'NG' => 'Nigeria', 'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway',
        'NP' => 'Nepal', 'NZ' => 'New Zealand', 'OM' => 'Oman', 'PA' => 'Panama',
        'PE' => 'Peru', 'PG' => 'Papua New Guinea', 'PH' => 'Philippines', 'PK' => 'Pakistan',
        'PL' => 'Poland', 'PR' => 'Puerto Rico', 'PS' => 'Palestine', 'PT' => 'Portugal',
        'PY' => 'Paraguay', 'QA' => 'Qatar', 'RO' => 'Romania', 'RS' => 'Serbia',
        'RU' => 'Russia', 'RW' => 'Rwanda', 'SA' => 'Saudi Arabia', 'SD' => 'Sudan',
        'SE' => 'Sweden', 'SG' => 'Singapore', 'SI' => 'Slovenia', 'SK' => 'Slovakia',
        'SN' => 'Senegal', 'SO' => 'Somalia', 'SV' => 'El Salvador', 'SY' => 'Syria',
        'TH' => 'Thailand', 'TJ' => 'Tajikistan', 'TM' => 'Turkmenistan', 'TN' => 'Tunisia',
        'TR' => 'Türkiye', 'TT' => 'Trinidad and Tobago', 'TW' => 'Taiwan', 'TZ' => 'Tanzania',
        'UA' => 'Ukraine', 'UG' => 'Uganda', 'US' => 'United States', 'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan', 'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen',
        'ZA' => 'South Africa', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    ];

    public static function name(?string $code): ?string
    {
        if (blank($code)) {
            return null;
        }

        return self::NAMES[strtoupper($code)] ?? strtoupper($code);
    }

    /** Regional indicator pair, e.g. PK -> 🇵🇰. Returns a globe for unknown codes. */
    public static function flag(?string $code): string
    {
        $code = strtoupper((string) $code);

        if (strlen($code) !== 2 || ! ctype_alpha($code)) {
            return '🌍';
        }

        return mb_chr(0x1F1E6 + (ord($code[0]) - 65), 'UTF-8')
            .mb_chr(0x1F1E6 + (ord($code[1]) - 65), 'UTF-8');
    }
}
