"""Build a reviewed price plan for existing WooCommerce products only."""
import json
from decimal import Decimal
from pathlib import Path
import openpyxl

root = Path(__file__).resolve().parents[1]
out = root / 'output/customer-polish'
book = openpyxl.load_workbook(Path('C:/Users/kamilgaraev/Downloads/Товары_11.08.2026.xlsx'), data_only=True)
rows = []
for row in list(book.active.values)[2:]:
    if not row[0] or not row[2] or not row[22]:
        continue
    rows.append({'article': str(row[0]), 'marketplace_sku': str(row[2]), 'name': str(row[4]),
                 'price': format(Decimal(str(row[22]).replace(',', '.')), '.2f')})
by_marketplace = {r['marketplace_sku']: r for r in rows}
mapping = {
    '200-70': '293540681', '200-80': '1016313630', '200-65-cinnamon': '843215030',
    '200-68-coriander': '1615164899', '100-70': '1560950169', '100-80': '1953176119',
    '100-65-cinnamon': '1953234990', '100-68-coriander': '1959763557',
    '100-goat': '3122646948', '100-cow': '3122648260', '200-goat': '3122648291', '200-cow': '3122648059',
    'cacao-100': '1617695779', 'cacao-200': '282828158', 'cacao-400': '1658099598',
    '30-goat': '3517000735', '30-whole-hazelnut': '3517021252', '30-hazelnut-raisin': '3517043164',
    '30-59-date': '3517056828', '30-59-cherry-almond': '3517078126', '30-date-powder': '3517093892',
    '30-59-cherry-buckwheat': '3517113783', '30-raspberry': '3517131116', '30-70': '3835428094', '30-80': '3835469871',
}
products = json.loads((out / 'audit.json').read_text(encoding='utf-8-sig'))['products']
plan, unmatched = [], []
single_30_prices = {r['price'] for r in rows if r['article'].endswith('30_1')}
assert len(single_30_prices) == 1, 'The new 30g product needs an unambiguous price.'
for product in products:
    sku = product['sku']
    match = by_marketplace.get(sku[5:]) if sku.startswith('ozon-') else by_marketplace.get(mapping.get(sku.removeprefix('theobroma-'), ''))
    if match:
        plan.append({'id': product['id'], 'sku': sku, 'name': product['name'], 'status': product['status'],
                     'old_price': product['price'], 'price': match['price'], 'source_article': match['article'], 'source_name': match['name']})
    elif product['id'] == 337 and not sku and product['name'] == 'Молочный шоколад 30г':
        plan.append({'id': product['id'], 'sku': sku, 'name': product['name'], 'status': product['status'],
                     'old_price': product['price'], 'price': next(iter(single_30_prices)),
                     'source_article': 'single-30g-common-price', 'source_name': 'Одинаковая цена всех одиночных шоколадок 30 г в таблице'})
    else:
        unmatched.append({'id': product['id'], 'sku': sku, 'name': product['name']})
(out / 'price-source.json').write_text(json.dumps(rows, ensure_ascii=False, indent=2), encoding='utf-8')
(out / 'price-plan.json').write_text(json.dumps({'source': book.active.title, 'product_count': len(products), 'updates': plan, 'unmatched': unmatched}, ensure_ascii=False, indent=2), encoding='utf-8')
changes = [p for p in plan if Decimal(p['old_price'] or '0') != Decimal(p['price'])]
print(json.dumps({'source_rows': len(rows), 'existing_products': len(products), 'matched': len(plan), 'changed': len(changes), 'unmatched': unmatched}, ensure_ascii=False))
