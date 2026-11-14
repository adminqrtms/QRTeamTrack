// Add / Edit Personnel form: address dropdowns, phone digits, password checklist.
(function () {
    // ---- Address: Province -> City / Municipality -> Barangay ----
    const box = document.getElementById('address-fields');
    if (box) {
        const province = document.getElementById('province');
        const city = document.getElementById('city');
        const barangay = document.getElementById('barangay');
        let provinces = [];

        const fill = (select, names, placeholder, selected) => {
            select.innerHTML = '';
            select.add(new Option(placeholder, ''));
            names.forEach(name => select.add(new Option(name, name, false, name === selected)));
            select.disabled = names.length === 0;
        };
        const citiesOf = name => (provinces.find(p => p.name === name) || { cities: [] }).cities;
        const barangaysOf = (prov, name) => (citiesOf(prov).find(c => c.name === name) || { barangays: [] }).barangays;

        const showCities = selected => {
            fill(city, citiesOf(province.value).map(c => c.name), 'Choose city / municipality', selected);
            if (!province.value) city.options[0].text = 'Choose province first';
        };
        const showBarangays = selected => {
            fill(barangay, barangaysOf(province.value, city.value), 'Choose barangay', selected);
            if (!city.value) barangay.options[0].text = 'Choose city first';
        };

        province.addEventListener('change', () => { showCities(''); showBarangays(''); });
        city.addEventListener('change', () => showBarangays(''));

        fetch(box.dataset.source)
            .then(r => r.json())
            .then(data => {
                provinces = data.provinces || [];
                fill(province, provinces.map(p => p.name), 'Choose province', box.dataset.province);
                showCities(box.dataset.city);
                showBarangays(box.dataset.barangay);
            })
            .catch(() => {
                province.innerHTML = '<option value="">Could not load the address list - refresh the page</option>';
            });
    }

    // ---- Phone: digits only, at most 11 ----
    const phone = document.getElementById('phone_number');
    if (phone) {
        phone.addEventListener('input', () => {
            const digits = phone.value.replace(/\D/g, '').slice(0, 11);
            if (digits !== phone.value) phone.value = digits;
        });
    }

    // ---- Hourly rate: whole pesos only, within the allowed range ----
    const rate = document.getElementById('hourly_rate');
    if (rate) {
        const min = Number(rate.dataset.min), max = Number(rate.dataset.max);
        const check = () => {
            // Keep the whole-peso part; 2 digits, or 3 only for 100
            let digits = rate.value.split(/[.,]/)[0].replace(/\D/g, '');
            digits = digits.slice(0, digits.startsWith('1') ? 3 : 2);
            if (digits !== rate.value) rate.value = digits;
            const n = Number(digits);
            rate.setCustomValidity(digits && (n < min || n > max)
                ? `Enter a rate from ${min} to ${max} pesos per hour.` : '');
        };
        rate.addEventListener('input', check);
        check();
    }

    // ---- Password: live checklist and match ----
    const password = document.getElementById('password');
    const confirm = document.getElementById('password_confirmation');
    const rules = document.getElementById('password-rules');
    const match = document.getElementById('password-match');
    if (password && rules) {
        const checks = {
            length: v => v.length >= 8,
            upper: v => /[A-Z]/.test(v),
            symbol: v => /[^A-Za-z0-9]/.test(v),
        };
        const update = () => {
            const v = password.value;
            let allOk = true;
            rules.querySelectorAll('li').forEach(li => {
                const ok = checks[li.dataset.rule](v);
                li.classList.toggle('ok', ok);
                allOk = allOk && ok;
            });
            // Browser blocks submitting a password that misses a rule (empty is fine when editing)
            password.setCustomValidity(v && !allOk ? 'Password needs 8+ characters, 1 capital letter and 1 symbol.' : '');

            if (confirm && match) {
                if (!confirm.value) {
                    match.textContent = '';
                    confirm.setCustomValidity('');
                } else if (confirm.value === v) {
                    match.textContent = '✓ Passwords match';
                    match.className = 'small mt-2 text-success';
                    confirm.setCustomValidity('');
                } else {
                    match.textContent = '✕ Passwords do not match';
                    match.className = 'small mt-2 text-danger';
                    confirm.setCustomValidity('Passwords do not match.');
                }
            }
        };
        password.addEventListener('input', update);
        if (confirm) confirm.addEventListener('input', update);
        update();
    }
})();
