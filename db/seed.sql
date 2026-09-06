-- ============================================================================
-- BookNest South Africa — Sample Data (run AFTER db/schema.sql)
-- Local South African books · prices in South African Rand (ZAR)
-- ============================================================================
USE bookstore;

-- ----------------------------------------------------------------------------
-- users  (passwords are bcrypt hashes generated with PHP password_hash())
--   admin@booknest.com  / Admin123!
--   demo@booknest.com   / Password1!
--   naledi.k@mail.com   / Bookworm22
-- ----------------------------------------------------------------------------
INSERT INTO users (Name, Email, Password, Role) VALUES
('Store Administrator', 'admin@booknest.com', '$2y$10$a5CAQ/A2Hf2ssC.GPt/NmOwd.EVxw18bhmMnIvQD8bDfTyS9JEs3W', 'Admin'),
('Demo Customer',       'demo@booknest.com',  '$2y$10$NWjCynvoaEJbjTmGlHAIye96rFLcYR8AmuomhhAXRqWAbMxj99L/S', 'Customer'),
('Naledi Khumalo',      'naledi.k@mail.com',  '$2y$10$QfihMitq3BoAi8uUZEjBU.UM5.ruLg1UOG0nhGu8JG/xNXXz3zDp2', 'Customer');

-- ----------------------------------------------------------------------------
-- books  (20 celebrated South African titles — covers rendered from initials)
-- ----------------------------------------------------------------------------
INSERT INTO books (Title, Author, Genre, ISBN, Price, Stock, Synopsis) VALUES
('Disgrace', 'J.M. Coetzee', 'Fiction', '9780140296400', 195.00, 18,
 'A Cape Town professor loses everything and retreats to his daughter''s Eastern Cape farm, where the certainties of the new South Africa unravel around him. Winner of the Booker Prize.'),
('Cry, the Beloved Country', 'Alan Paton', 'Fiction', '9780099766810', 165.00, 26,
 'Stephen Kumalo leaves his rural village for Johannesburg to find his son — a landmark novel of love, fear and hope in a changing South Africa.'),
('Burger''s Daughter', 'Nadine Gordimer', 'Fiction', '9780747501873', 235.00, 9,
 'Rosa Burger must come to terms with the revolutionary legacy of her father in this novel by South Africa''s first Nobel literature laureate.'),
('Spud', 'John van de Ruit', 'Fiction', '9780143025032', 155.00, 30,
 'The hilarious boarding-school diary of Spud Milton — wit, cricket and chaos at a KwaZulu-Natal private school in 1990.'),
('A Dry White Season', 'André Brink', 'Fiction', '9780099908708', 185.00, 12,
 'A white schoolteacher investigates the death in detention of a black groundskeeper and is drawn into the full horror of the apartheid state.'),
('Tsotsi', 'Athol Fugard', 'Fiction', '9780143188676', 145.00, 21,
 'A Johannesburg gang leader''s life is overturned when a robbery goes wrong and he is left holding a baby — the basis of the Oscar-winning film.'),
('The Story of an African Farm', 'Olive Schreiner', 'Fiction', '9780143104935', 170.00, 14,
 'The pioneering 1883 novel of two girls growing up on a Karoo farm, questioning God, love and the freedom of women.'),
('Ways of Dying', 'Zakes Mda', 'Fiction', '9780312147509', 165.00, 8,
 'Toloki the professional mourner and Noria the healer meet in a township riven by violence — a magical, heartbreaking fable of reconciliation.'),
('Devil''s Peak', 'Deon Meyer', 'Fiction', '9780340935884', 190.00, 16,
 'Vigilante killer, detective and a woman on the run converge in this acclaimed Cape Town crime thriller by Deon Meyer.'),
('Zoo City', 'Lauren Beukes', 'Fiction', '9781408803233', 180.00, 11,
 'In a Johannesburg where criminals acquire magical animal familiars, a witch with a sloth named Sloth takes one last job. Winner of the Arthur C. Clarke Award.'),
('Thirteen Cents', 'K. Sello Duiker', 'Fiction', '9780795701232', 175.00, 7,
 'Azure, a thirteen-year-old street child in Cape Town, survives the city''s underside in this searing and poetic debut.'),
('Long Walk to Freedom', 'Nelson Mandela', 'Non-fiction', '9780349106535', 320.00, 24,
 'The autobiography of Nelson Mandela — from a rural boyhood, through twenty-seven years in prison, to the presidency of a democratic South Africa.'),
('Born a Crime', 'Trevor Noah', 'Non-fiction', '9781473635302', 250.00, 33,
 'Trevor Noah''s wild, funny memoir of growing up in South Africa as a mixed-race kid during the last years of apartheid.'),
('Country of My Skull', 'Antjie Krog', 'Non-fiction', '9780812966688', 215.00, 10,
 'Antjie Krog''s searing account of covering the Truth and Reconciliation Commission for South African radio.'),
('A Human Being Died That Night', 'Pumla Gobodo-Madikizela', 'Non-fiction', '9780618211894', 185.00, 13,
 'A white psychologist interviews apartheid''s death-squad commander Eugene de Kock — a profound dialogue about guilt, forgiveness and humanity.'),
('My Traitor''s Heart', 'Rian Malan', 'Non-fiction', '9780871137881', 225.00, 6,
 'Rian Malan, descendant of Afrikaner patriarchs, returns to a violent South Africa to understand his family''s history and his own heart.'),
('The Day Gogo Went to Vote', 'Elinor Batezat Sisulu', 'Children''s', '9780316702722', 115.00, 40,
 'Thembi''s grandmother Gogo is determined to cast her first vote in South Africa''s first democratic election — a family story of history in the making.'),
('Madiba Magic', 'Nelson Mandela (comp.)', 'Children''s', '9780624043353', 195.00, 22,
 'Nelson Mandela''s favourite stories for children, collected with African folk tales of clever animals and brave heroes.'),
('Liewe Heksie', 'Verna Vels', 'Children''s', '9780798147194', 145.00, 28,
 'The beloved Afrikaans children''s classic about the lovable witch Heksie and her misadventures with Blommie and Adam.'),
('My First Book of Southern African Birds', 'Erroll Cuthbert', 'Children''s', '9781770074215', 129.00, 35,
 'A bright first field guide to the birds children see every day across Southern Africa — from hadedas to sunbirds.');

-- ----------------------------------------------------------------------------
-- promotions  (SA Book Month etc.)
-- ----------------------------------------------------------------------------
INSERT INTO promotions (Description, DiscountCode, DiscountValue, ValidityStart, ValidityEnd) VALUES
('SA Book Month Sale — 30% off everything', 'READ30', 30.00, '2026-09-01 00:00:00', '2026-12-31 23:59:59'),
('Welcome offer — 10% off your first order', 'WELCOME10', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59'),
('Reading week — 15% off Non-fiction', 'NONFIC15', 15.00, '2026-09-01 00:00:00', '2026-12-31 23:59:59');

-- ----------------------------------------------------------------------------
-- orders + order_details
--   Order 1 (Paid):   Demo Customer — 2x Disgrace + 1x Cry the Beloved Country,
--                     READ30 applied, free standard delivery (subtotal > R500)
--   Order 2 (Pending): Naledi — Born a Crime + Country of My Skull, standard courier
-- ----------------------------------------------------------------------------
INSERT INTO orders (OrderID, UserID, OrderDate, TotalAmount, Status, ShipName, ShipAddress, ShipCity, ShipPostcode, ShipCountry, ShipPhone, ShipMethod, ShipCost, EstimatedDelivery, PromoCode, DiscountAmount) VALUES
(1, 2, '2026-09-02 10:15:00', 388.50, 'Paid', 'Demo Customer', '10 Main Road, Rosebank', 'Johannesburg', '2196', 'South Africa', '+27 82 555 0100', 'Standard', 0.00, '2026-09-08', 'READ30', 166.50),
(2, 3, '2026-09-04 14:42:00', 544.00, 'Pending', 'Naledi Khumalo', '12 Kloof Street, Gardens', 'Cape Town', '8001', 'South Africa', '+27 83 555 0200', 'Standard', 79.00, '2026-09-10', NULL, 0.00);

INSERT INTO order_details (OrderID, BookID, Quantity, PriceAtPurchase) VALUES
(1, 1, 2, 195.00),
(1, 2, 1, 165.00),
(2, 13, 1, 250.00),
(2, 14, 1, 215.00);

-- ----------------------------------------------------------------------------
-- reviews
-- ----------------------------------------------------------------------------
INSERT INTO reviews (UserID, BookID, Rating, Comment, ReviewDate) VALUES
(2, 1, 5, 'Brutal and beautiful — Coetzee at his finest. The Eastern Cape setting is unforgettable.', '2026-09-05 19:20:00'),
(3, 1, 4, 'Devastating read. Not an easy book, but an essential one.', '2026-08-28 12:05:00'),
(3, 6, 5, 'Tsotsi''s journey from gangster to guardian stayed with me for weeks.', '2026-08-20 09:40:00'),
(2, 13, 5, 'Hilarious and heartbreaking. Trevor Noah tells South Africa''s story like no one else.', '2026-09-06 21:15:00'),
(3, 12, 5, 'Reading Mandela''s own words about his 27 years in prison is humbling.', '2026-07-02 08:00:00'),
(2, 20, 4, 'Beautiful pictures — my kids now spot hadedas and sunbirds on every walk.', '2026-08-15 17:45:00');

-- ----------------------------------------------------------------------------
-- wishlist_items
-- ----------------------------------------------------------------------------
INSERT INTO wishlist_items (UserID, BookID, DateAdded) VALUES
(2, 10, '2026-09-06 11:30:00'),
(2, 16, '2026-09-06 11:31:00'),
(3, 4,  '2026-09-01 16:10:00');

