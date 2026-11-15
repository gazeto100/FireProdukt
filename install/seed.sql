-- FireProdukt: ПРИМЕРНО съдържание (цени, тегла, текстове) — заменете го от админ панела.

INSERT INTO settings (k, v) VALUES
 ('site_name', 'FireProdukt'),
 ('currency', '€'),
 ('show_prices', '0'),
 ('min_letter_mm', '30'),
 ('inquiry_rate_limit', '30'),
 ('contact_phone', ''),
 ('meta_pixel_id', ''),
 ('ga4_id', ''),
 ('mail_from', ''),
 ('mail_from_name', 'FireProdukt'),
 ('smtp_host', ''),
 ('smtp_port', '587'),
 ('smtp_secure', 'tls'),
 ('smtp_user', ''),
 ('smtp_pass', ''),
 ('schema_version', '3')
ON DUPLICATE KEY UPDATE k = k;

INSERT INTO product_types (code, name, hint, sort) VALUES
 ('firepit', 'Огнище', 'За двора, вилата и дълги вечери край огъня', 1),
 ('grill',   'Скара / мангал', 'За печене – у дома или на пикник', 2);

INSERT INTO option_groups (code, name, hint, multi, required, sort) VALUES
 ('material', 'Материал', NULL, 0, 1, 3),
 ('legs',     'Крачета', NULL, 0, 1, 4),
 ('font',     'Шрифт', NULL, 0, 1, 5),
 ('sides',    'На колко страни', 'Надписът и орнаментът се изрязват на избраните страни', 0, 1, 5),
 ('ornament', 'Орнамент', NULL, 0, 1, 6),
 ('extras',   'Екстри', 'Може да изберете няколко', 1, 0, 7);

-- Материали: price_type factor -> умножава базовата цена на модела
INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort)
SELECT id, 'inox2', 'Инокс 2 мм', 'Неръждаема стомана, шлайфана', 'factor', 1.00, 1.00, 0, '{"finish":"inox","thickness_mm":2,"density":7.93}', 1 FROM option_groups WHERE code='material' UNION ALL
SELECT id, 'inox3', 'Инокс 3 мм', 'По-масивен и по-устойчив на деформация', 'factor', 1.30, 1.35, 0, '{"finish":"inox","thickness_mm":3,"density":7.93}', 2 FROM option_groups WHERE code='material' UNION ALL
SELECT id, 'black3', 'Черна стомана 3 мм', 'С прахово боядисване, матово черно', 'factor', 0.75, 0.80, 0, '{"finish":"black","thickness_mm":3,"density":7.85}', 3 FROM option_groups WHERE code='material';

INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort)
SELECT id, 'none', 'Без крачета', 'Стъпва директно на земята', 'add', 0, 0, 0, '{"leg_cm":0}', 1 FROM option_groups WHERE code='legs' UNION ALL
SELECT id, 'low', 'Ниски 10 см', 'Пазят земята и тревата от жарта', 'add', 20, 30, 1.2, '{"leg_cm":10}', 2 FROM option_groups WHERE code='legs' UNION ALL
SELECT id, 'high', 'Високи 75 см', 'Удобна работна височина', 'add', 60, 85, 4.5, '{"leg_cm":75}', 3 FROM option_groups WHERE code='legs';

INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort)
SELECT id, 'russo', 'Технически', 'Russo One', 'add', 0, 0, 0, '{"font":"russo"}', 1 FROM option_groups WHERE code='font' UNION ALL
SELECT id, 'oswald', 'Тесен', 'Oswald – събира повече знаци', 'add', 0, 0, 0, '{"font":"oswald"}', 2 FROM option_groups WHERE code='font' UNION ALL
SELECT id, 'dela', 'Масивен', 'Dela Gothic One', 'add', 0, 0, 0, '{"font":"dela"}', 3 FROM option_groups WHERE code='font';

INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort)
SELECT id, 's1', '1 страна', NULL, 'add', 25, 35, 0, '{"sides":1}', 1 FROM option_groups WHERE code='sides' UNION ALL
SELECT id, 's2', '2 страни', NULL, 'add', 45, 65, 0, '{"sides":2}', 2 FROM option_groups WHERE code='sides' UNION ALL
SELECT id, 'sall', 'Всички страни', NULL, 'add', 80, 120, 0, '{"sides":"all"}', 3 FROM option_groups WHERE code='sides';

-- Орнамент: per_side -> цената се умножава по броя декорирани страни
INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort)
SELECT id, 'none', 'Без орнамент', NULL, 'add', 0, 0, 0, '{"pattern":null}', 1 FROM option_groups WHERE code='ornament' UNION ALL
SELECT id, 'flames', 'Пламъци', NULL, 'add', 15, 25, 0, '{"pattern":"flames","per_side":true}', 2 FROM option_groups WHERE code='ornament' UNION ALL
SELECT id, 'mountains', 'Планини', NULL, 'add', 15, 25, 0, '{"pattern":"mountains","per_side":true}', 3 FROM option_groups WHERE code='ornament' UNION ALL
SELECT id, 'pines', 'Борове', NULL, 'add', 15, 25, 0, '{"pattern":"pines","per_side":true}', 4 FROM option_groups WHERE code='ornament' UNION ALL
SELECT id, 'shevitsa', 'Шевица', NULL, 'add', 20, 30, 0, '{"pattern":"shevitsa","per_side":true}', 5 FROM option_groups WHERE code='ornament';

INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort)
SELECT id, 'grate', 'Скара за печене', 'Решетка от инокс върху огнището', 'add', 45, 65, 3.0, '{"extra":"grate"}', 1 FROM option_groups WHERE code='extras' UNION ALL
SELECT id, 'arm', 'Рамо за котле', 'Въртящо се рамо с кука', 'add', 55, 75, 4.0, '{"extra":"arm"}', 2 FROM option_groups WHERE code='extras' UNION ALL
SELECT id, 'side_table', 'Страничен плот', 'Място за чинии и прибори', 'add', 40, 60, 3.5, '{"extra":"side_table"}', 3 FROM option_groups WHERE code='extras' UNION ALL
SELECT id, 'lid', 'Капак', 'Гаси огъня и пази от дъжд', 'add', 70, 95, 5.0, '{"extra":"lid"}', 4 FROM option_groups WHERE code='extras' UNION ALL
SELECT id, 'bag', 'Калъф за пренасяне', 'Брезентов калъф с дръжки', 'add', 25, 35, 0.6, '{"extra":"bag"}', 5 FROM option_groups WHERE code='extras';

-- Модели
-- shape: box = правоъгълно огнище; hex = шестоъгълно (width_cm = диаметър през ъглите); grill = мангал / скара
INSERT INTO models (type_id, code, name, shape, width_cm, depth_cm, height_cm, max_chars, price_min, price_max, description, sort)
SELECT id, 'sq_s', 'Огнище квадратно S', 'box', 40, 40, 30, 11, 220, 290, 'Компактно – за тераса или малък двор.', 1 FROM product_types WHERE code='firepit' UNION ALL
SELECT id, 'sq_m', 'Огнище квадратно M', 'box', 50, 50, 35, 14, 280, 360, 'Най-търсеният размер – за 4–6 души около огъня.', 2 FROM product_types WHERE code='firepit' UNION ALL
SELECT id, 'sq_l', 'Огнище квадратно L', 'box', 60, 60, 40, 16, 360, 460, 'Голямо огнище за компания и дълги вечери.', 3 FROM product_types WHERE code='firepit' UNION ALL
SELECT id, 'hex60', 'Огнище шестоъгълно Ø60', 'hex', 60, 60, 35, 9, 340, 440, 'Шест стени за надпис и орнамент – огънят се вижда отвсякъде.', 4 FROM product_types WHERE code='firepit' UNION ALL
SELECT id, 'mangal_picnic', 'Мангал за пикник', 'grill', 40, 25, 12, 10, 90, 120, 'Лек мангал, който се пренася в калъф.', 5 FROM product_types WHERE code='grill' UNION ALL
SELECT id, 'mangal_std', 'Мангал стандартен', 'grill', 50, 30, 15, 14, 120, 160, 'Класически мангал за скара на дървени въглища.', 6 FROM product_types WHERE code='grill' UNION ALL
SELECT id, 'grill_stand', 'Скара на стойка', 'grill', 60, 40, 15, 16, 260, 340, 'Скара на висока стойка – печете прав, без да се навеждате.', 7 FROM product_types WHERE code='grill';

-- Съвместимост: огнищата (квадратни S, M, L и шестоъгълно)
INSERT INTO model_options (model_id, option_id, is_default)
SELECT m.id, o.id,
  CASE WHEN (g.code, o.code) IN (('material','inox2'),('legs','none'),('font','russo'),('sides','s1'),('ornament','none')) THEN 1 ELSE 0 END
FROM models m
JOIN options o
JOIN option_groups g ON g.id = o.group_id
WHERE m.code IN ('sq_s', 'sq_m', 'sq_l', 'hex60')
  AND (g.code IN ('material','font','sides','ornament')
       OR (g.code = 'legs' AND o.code IN ('none','low'))
       OR (g.code = 'extras' AND o.code IN ('grate','arm','lid')));

-- Съвместимост: мангал стандартен (високи крачета, страничен плот; без рамо за котле)
INSERT INTO model_options (model_id, option_id, is_default)
SELECT m.id, o.id,
  CASE WHEN (g.code, o.code) IN (('material','inox2'),('legs','low'),('font','russo'),('sides','s1'),('ornament','none')) THEN 1 ELSE 0 END
FROM models m
JOIN options o
JOIN option_groups g ON g.id = o.group_id
WHERE m.code = 'mangal_std'
  AND (g.code IN ('material','legs','font','sides','ornament')
       OR (g.code = 'extras' AND o.code IN ('grate','side_table','lid')));

-- Мангалът е по-малък: по-ниски цени за надпис, орнамент, капак и крачета (пример за цена по модел)
UPDATE model_options mo
JOIN models m ON m.id = mo.model_id
JOIN options o ON o.id = mo.option_id
JOIN option_groups g ON g.id = o.group_id
SET mo.price_min = CASE CONCAT(g.code, '.', o.code)
      WHEN 'sides.s1' THEN 15 WHEN 'sides.s2' THEN 30 WHEN 'sides.sall' THEN 50
      WHEN 'extras.lid' THEN 35 WHEN 'extras.grate' THEN 25 ELSE mo.price_min END,
    mo.price_max = CASE CONCAT(g.code, '.', o.code)
      WHEN 'sides.s1' THEN 25 WHEN 'sides.s2' THEN 45 WHEN 'sides.sall' THEN 75
      WHEN 'extras.lid' THEN 50 WHEN 'extras.grate' THEN 35 ELSE mo.price_max END
WHERE m.code = 'mangal_std';

-- Съвместимост: мангал за пикник (без крачета или ниски; скара и калъф)
INSERT INTO model_options (model_id, option_id, is_default)
SELECT m.id, o.id,
  CASE WHEN (g.code, o.code) IN (('material','inox2'),('legs','none'),('font','russo'),('sides','s1'),('ornament','none')) THEN 1 ELSE 0 END
FROM models m
JOIN options o
JOIN option_groups g ON g.id = o.group_id
WHERE m.code = 'mangal_picnic'
  AND (g.code IN ('material','font','sides','ornament')
       OR (g.code = 'legs' AND o.code IN ('none','low'))
       OR (g.code = 'extras' AND o.code IN ('grate','bag')));

UPDATE model_options mo
JOIN models m ON m.id = mo.model_id
JOIN options o ON o.id = mo.option_id
JOIN option_groups g ON g.id = o.group_id
SET mo.price_min = CASE CONCAT(g.code, '.', o.code)
      WHEN 'sides.s1' THEN 10 WHEN 'sides.s2' THEN 20 WHEN 'sides.sall' THEN 35
      WHEN 'extras.grate' THEN 20 WHEN 'legs.low' THEN 15 ELSE mo.price_min END,
    mo.price_max = CASE CONCAT(g.code, '.', o.code)
      WHEN 'sides.s1' THEN 18 WHEN 'sides.s2' THEN 32 WHEN 'sides.sall' THEN 55
      WHEN 'extras.grate' THEN 30 WHEN 'legs.low' THEN 22 ELSE mo.price_max END
WHERE m.code = 'mangal_picnic';

-- Съвместимост: скара на стойка (само високи крачета – стойката е включена в цената)
INSERT INTO model_options (model_id, option_id, is_default)
SELECT m.id, o.id,
  CASE WHEN (g.code, o.code) IN (('material','inox2'),('legs','high'),('font','russo'),('sides','s1'),('ornament','none')) THEN 1 ELSE 0 END
FROM models m
JOIN options o
JOIN option_groups g ON g.id = o.group_id
WHERE m.code = 'grill_stand'
  AND (g.code IN ('material','font','sides','ornament')
       OR (g.code = 'legs' AND o.code = 'high')
       OR (g.code = 'extras' AND o.code IN ('grate','side_table','lid')));

UPDATE model_options mo
JOIN models m ON m.id = mo.model_id
JOIN options o ON o.id = mo.option_id
JOIN option_groups g ON g.id = o.group_id
SET mo.price_min = 0, mo.price_max = 0
WHERE m.code = 'grill_stand' AND g.code = 'legs';
