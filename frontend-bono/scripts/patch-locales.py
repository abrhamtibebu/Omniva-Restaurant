#!/usr/bin/env python3
import json
from pathlib import Path

base = Path(__file__).resolve().parents[1] / 'i18n' / 'locales'

patches = {
    'en': {
        'common.elapsedMinutes': '{n} min',
        'common.elapsedHours': '{h}h {m}m',
        'common.amount': 'Amount',
        'language.am': 'አማርኛ',
        'middleware.entitlementInactive': 'Bono control is not active for this restaurant.',
        'middleware.accessDenied': 'Access denied',
        'middleware.accessDeniedDetail': 'You do not have permission to open that page.',
    },
    'am': {
        'common.elapsedMinutes': '{n} ደቂቃ',
        'common.elapsedHours': '{h}ሰ {m}ደ',
        'common.amount': 'መጠን',
        'language.am': 'አማርኛ',
        'language.en': 'English',
        'middleware.entitlementInactive': 'ለዚህ ሬስቶራንት ቦኖ ቁጥጥር አይሰራም።',
        'middleware.accessDenied': 'መዳረሻ ተከልክሏል',
        'middleware.accessDeniedDetail': 'ይህን ገጽ ለመክፈት ፈቃድ የለዎትም።',
    },
}


def set_path(data: dict, dotted: str, value: str) -> None:
    parts = dotted.split('.')
    cur = data
    for part in parts[:-1]:
        cur = cur.setdefault(part, {})
    cur[parts[-1]] = value


def leaf_keys(d: dict, prefix: str = '') -> set[str]:
    out: set[str] = set()
    for key, value in d.items():
        path = f'{prefix}.{key}' if prefix else key
        if isinstance(value, dict):
            out |= leaf_keys(value, path)
        else:
            out.add(path)
    return out


for locale, items in patches.items():
    path = base / f'{locale}.json'
    data = json.loads(path.read_text(encoding='utf-8'))
    for key, value in items.items():
        set_path(data, key, value)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

en = json.loads((base / 'en.json').read_text(encoding='utf-8'))
am = json.loads((base / 'am.json').read_text(encoding='utf-8'))
assert leaf_keys(en) == leaf_keys(am), sorted(leaf_keys(en) ^ leaf_keys(am))
print('patched', len(leaf_keys(en)), 'keys')
