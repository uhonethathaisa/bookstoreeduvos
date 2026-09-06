# User Manual — BookNest Online Bookstore

> Start the site with `php -S localhost:8000 -t public` and open **http://localhost:8000**
> in Chrome/Edge/Firefox. Screenshots referenced below live in `docs/screenshots/app/`.

---

## 1. Register / Log in

1. Click **Register** (top-right) and enter your full name, e-mail, and a password (min. 8 characters).
2. Confirm the password and click **Register** — you are logged in automatically.
3. Returning users click **Log in**. Demo accounts:
   - Customer `demo@booknest.com` / `Password1!`
   - Admin `admin@booknest.com` / `Admin123!`

**Screenshot:** `01-home.png`, `login.png`, `register.png`

## 2. Browse the catalogue

1. From the homepage choose a category (**Fiction / Non-fiction / Children's**) or use the **All books** link.
2. **Search:** type in the header search box — suggestions appear as you type (autocomplete). Press Enter to run a full search, or click a suggestion to open that book.
3. **Filter & sort:** use the left panel to filter by genre, author, minimum rating and price range.
   The results update **without a page reload**; sort by Bestsellers / New arrivals / Price.
4. Browse further pages with **Prev / Next / page numbers**.

**Screenshot:** `catalogue.png`, `catalogue-filtered.png`

## 3. View book details

1. Click any book cover or title.
2. You see the cover, price, ISBN, genre, stock, synopsis, a preview excerpt and the **rating summary**.
3. Below are **customer reviews** (name, stars, date, comment).
4. Logged-in customers can **write a review**: pick 1–5 stars, add a comment, click **Submit review**.
5. Click **♥ Add to wishlist** to save the book for later, or set a quantity and **Add to cart**.

**Screenshot:** `book-details.png`, `book-reviews.png`

## 4. Shopping cart

1. Open **Cart 🛒** in the header.
2. Change quantities and click **Update quantities**; use **Remove** or **Clear cart**.
3. Enter a promo code (try **READ30** — 30% off) and click **Apply**. The discount and shipping (free over **R500**) appear in the order summary with the new total.
4. Click **Proceed to checkout**.

**Screenshot:** `cart.png`, `cart-promo.png`

## 5. Checkout (payment simulation)

1. Checkout requires a login — you will be redirected to log in first if needed.
2. Complete the **delivery details** (name, address, city, postcode, country, phone).
3. Choose a **delivery method** (priced in rand, delivered anywhere in South Africa):
   - **Standard courier** R79 (free over R500) — 3–5 working days nationwide
   - **Express courier** R129 — 1–2 working days
   - **Priority same-day** R199 — Joburg / Cape Town / Durban metro
   The order summary updates instantly and the estimated delivery date appears on the receipt.
4. Enter card details. The gateway is **simulated**:
   - Cards starting with **4** (Visa) or **5** (Mastercard) are **authorised**, e.g. `4111 1111 1111 1111`, expiry `12/28`, CVV `123`.
   - Any other prefix is **declined** so you can see the error path.
5. Validation runs **in the browser** (inline messages) **and again on the server**.
6. Click **Place order**. A receipt page confirms the order with shipping method, cost and ETA, and a simulated confirmation e-mail is appended to `storage/email.log`.

**Screenshot:** `checkout.png`, `confirmation.png`

## 6. Track your delivery

1. From **My profile → Order history** click **Track** on any order (or **Track delivery** on the receipt).
2. The tracking page shows the status badge, delivery method, estimated delivery date and tracking number.
3. A **timeline** shows progress: *Order placed → Payment confirmed → Shipped (carrier + tracking number) → Delivered*.
4. As the **admin** updates the status (below), the customer timeline advances and a simulated
   notification e-mail is written to `storage/email.log`.

**Screenshot:** `track-delivery.png`

## 7. Profile, wishlist & order history

- **My profile:** update your name/e-mail, or change your password (enter your current password first).
- **Order history:** every order with status badge; click **View** to open the receipt.
- **Wishlist:** add/remove saved books; **Add to cart** moves an item into the basket.

**Screenshot:** `profile.png`, `wishlist.png`

## 8. Admin panel

Log in as the **Admin** account and click **Admin** in the header (or the footer link).

| Section | How to use |
|---|---|
| **Dashboard** | KPIs (revenue, orders, users, books, stock warnings), a *sales by genre* chart, top sellers and recent orders |
| **Books** | **＋ Add new book** opens the form; edit/delete per row; use the search box to find a title. Books referenced by orders/reviews are protected (integrity message). |
| **Orders** | Open any order for line items + delivery address and shipping method/cost/ETA. Change the **Status** dropdown — set **Shipped** to record carrier + tracking (auto-generated if blank) and **Completed** to mark delivered. Each transition notifies the customer. The list view also has an inline status changer and expandable items. |
| **Users** | Search customers, view joined date/order/review counts; **Remove** customers without history (FK-guarded). |
| **Promotions** | Create/edit/delete discount codes with % value and validity window; badges show Active/Upcoming/Expired. |

**Screenshot:** `admin-dashboard.png`, `admin-books.png`, `admin-orders.png`, `admin-promotions.png`

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Pages error / "database not available" | Run `db/schema.sql` + `db/seed.sql` (see README) and verify `bookstore_user` exists |
| Autocomplete or filters do nothing | Open the browser console — JS fetches `api/*` relative to the page; serve via `php -S` with `public` as docroot |
| Cannot delete a book/user | Expected — referential integrity blocks deletions that would orphan orders/reviews (a feature, not a bug) |
