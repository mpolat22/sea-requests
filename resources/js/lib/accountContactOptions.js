export const dialCodes = [
    { label: 'Afghanistan (+93)', value: '+93' }, { label: 'Albania (+355)', value: '+355' },
    { label: 'Algeria (+213)', value: '+213' }, { label: 'Andorra (+376)', value: '+376' },
    { label: 'Angola (+244)', value: '+244' }, { label: 'Argentina (+54)', value: '+54' },
    { label: 'Armenia (+374)', value: '+374' }, { label: 'Australia (+61)', value: '+61' },
    { label: 'Austria (+43)', value: '+43' }, { label: 'Azerbaijan (+994)', value: '+994' },
    { label: 'Bahrain (+973)', value: '+973' }, { label: 'Bangladesh (+880)', value: '+880' },
    { label: 'Belarus (+375)', value: '+375' }, { label: 'Belgium (+32)', value: '+32' },
    { label: 'Bosnia and Herzegovina (+387)', value: '+387' }, { label: 'Brazil (+55)', value: '+55' },
    { label: 'Bulgaria (+359)', value: '+359' }, { label: 'Canada (+1)', value: '+1' },
    { label: 'Chile (+56)', value: '+56' }, { label: 'China (+86)', value: '+86' },
    { label: 'Colombia (+57)', value: '+57' }, { label: 'Croatia (+385)', value: '+385' },
    { label: 'Cyprus (+357)', value: '+357' }, { label: 'Czech Republic (+420)', value: '+420' },
    { label: 'Denmark (+45)', value: '+45' }, { label: 'Egypt (+20)', value: '+20' },
    { label: 'Estonia (+372)', value: '+372' }, { label: 'Finland (+358)', value: '+358' },
    { label: 'France (+33)', value: '+33' }, { label: 'Georgia (+995)', value: '+995' },
    { label: 'Germany (+49)', value: '+49' }, { label: 'Greece (+30)', value: '+30' },
    { label: 'Hong Kong (+852)', value: '+852' }, { label: 'Hungary (+36)', value: '+36' },
    { label: 'India (+91)', value: '+91' }, { label: 'Indonesia (+62)', value: '+62' },
    { label: 'Iran (+98)', value: '+98' }, { label: 'Iraq (+964)', value: '+964' },
    { label: 'Ireland (+353)', value: '+353' }, { label: 'Israel (+972)', value: '+972' },
    { label: 'Italy (+39)', value: '+39' }, { label: 'Japan (+81)', value: '+81' },
    { label: 'Jordan (+962)', value: '+962' }, { label: 'Russia (+7)', value: '+7' },
    { label: 'Kuwait (+965)', value: '+965' }, { label: 'Latvia (+371)', value: '+371' },
    { label: 'Lebanon (+961)', value: '+961' }, { label: 'Libya (+218)', value: '+218' },
    { label: 'Lithuania (+370)', value: '+370' }, { label: 'Luxembourg (+352)', value: '+352' },
    { label: 'Malaysia (+60)', value: '+60' }, { label: 'Malta (+356)', value: '+356' },
    { label: 'Mexico (+52)', value: '+52' }, { label: 'Moldova (+373)', value: '+373' },
    { label: 'Montenegro (+382)', value: '+382' }, { label: 'Morocco (+212)', value: '+212' },
    { label: 'Netherlands (+31)', value: '+31' }, { label: 'New Zealand (+64)', value: '+64' },
    { label: 'Nigeria (+234)', value: '+234' }, { label: 'North Macedonia (+389)', value: '+389' },
    { label: 'Norway (+47)', value: '+47' }, { label: 'Oman (+968)', value: '+968' },
    { label: 'Pakistan (+92)', value: '+92' }, { label: 'Philippines (+63)', value: '+63' },
    { label: 'Poland (+48)', value: '+48' }, { label: 'Portugal (+351)', value: '+351' },
    { label: 'Qatar (+974)', value: '+974' }, { label: 'Romania (+40)', value: '+40' },
    { label: 'Kazakhstan (+7)', value: '+7' }, { label: 'Saudi Arabia (+966)', value: '+966' },
    { label: 'Serbia (+381)', value: '+381' }, { label: 'Singapore (+65)', value: '+65' },
    { label: 'Slovakia (+421)', value: '+421' }, { label: 'Slovenia (+386)', value: '+386' },
    { label: 'South Africa (+27)', value: '+27' }, { label: 'South Korea (+82)', value: '+82' },
    { label: 'Spain (+34)', value: '+34' }, { label: 'Sri Lanka (+94)', value: '+94' },
    { label: 'Sweden (+46)', value: '+46' }, { label: 'Switzerland (+41)', value: '+41' },
    { label: 'Syria (+963)', value: '+963' }, { label: 'Taiwan (+886)', value: '+886' },
    { label: 'Thailand (+66)', value: '+66' }, { label: 'Tunisia (+216)', value: '+216' },
    { label: 'Turkey (+90)', value: '+90' }, { label: 'Ukraine (+380)', value: '+380' },
    { label: 'United Arab Emirates (+971)', value: '+971' }, { label: 'United Kingdom (+44)', value: '+44' },
    { label: 'United States (+1)', value: '+1' }, { label: 'Uruguay (+598)', value: '+598' },
    { label: 'Uzbekistan (+998)', value: '+998' }, { label: 'Vietnam (+84)', value: '+84' },
    { label: 'Yemen (+967)', value: '+967' },
];

export const countryOptions = dialCodes.map((item) => {
    const country = item.label.replace(/\s*\(\+\d+\)$/, '');

    return {
        label: country,
        value: country,
    };
});

export const sanitizePhoneDigits = (value) => String(value ?? '').replace(/\D+/g, '').slice(0, 15);

export const parseDialPhone = (value, fallback = '') => {
    const match = String(value ?? '').trim().match(/^(\+\d{1,4})\s*(.+)$/);

    if (!match) {
        return { code: fallback, number: '' };
    }

    return {
        code: match[1],
        number: sanitizePhoneDigits(match[2]),
    };
};
