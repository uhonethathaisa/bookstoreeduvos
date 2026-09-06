# 05 — Data Flow Diagram (DFD)

## Level 0 reminder
See [04-context-diagram.md](04-context-diagram.md) — a single process `0 — Online Bookstore System`.

## Level 1 — Customer / checkout processes (`1.0`–`6.0`)

```mermaid
flowchart LR
    C(["Customer"])
    PG(["Payment Gateway"])
    ES(["Email Service"])

    P1("1.0 Browse /<br/>Search Catalogue")
    P2("2.0 View Details /<br/>Submit Review")
    P3("3.0 Manage Cart")
    P4("4.0 Checkout &<br/>Create Order")
    P5("5.0 Process Payment")
    P6("6.0 Send Email<br/>Notification")

    D1[("D1 Users")]
    D2[("D2 Books")]
    D3[("D3 Reviews")]
    D4[("D4 Orders /<br/>Order Details")]
    D5[("D5 Promotions")]
    D6[("D6 Cart<br/>(session, transient)")]

    C -->|"search / filter criteria"| P1
    P1 -->|"query"| D2
    D2 -->|"matching books"| P1
    P1 -->|"results list"| C

    C -->|"select book"| P2
    P2 -->|"read book info"| D2
    P2 -->|"read / write review"| D3
    D2 -->|"book data"| P2
    P2 -->|"details + reviews + rating"| C

    C -->|"add / remove / change qty"| P3
    P3 -->|"read stock & price"| D2
    P3 -->|"update items"| D6
    P3 -->|"updated totals"| C

    C -->|"checkout + promo code"| P4
    P4 -->|"customer details"| D1
    P4 -->|"validate code"| D5
    P4 -->|"cart contents"| D6
    P4 -->|"write order + lines"| D4
    P4 -->|"payment request"| P5
    P5 -->|"authorise transaction"| PG
    PG -->|"payment result"| P5
    P5 -->|"payment status"| P4
    P4 -->|"order confirmation (id, status, items)"| C
    P4 -->|"confirmation payload"| P6
    P6 -->|"send order e-mail"| ES
    D4 -->|"stored order"| P4
```

## Level 1 — Administration processes (`7.0`–`10.0`)

```mermaid
flowchart LR
    A(["Admin"])

    P7("7.0 Manage Inventory")
    P8("8.0 Fulfil Orders")
    P9("9.0 Manage Promotions")
    P10("10.0 View Analytics")

    D1[("D1 Users")]
    D2[("D2 Books")]
    D4[("D4 Orders /<br/>Order Details")]
    D5[("D5 Promotions")]

    A -->|"add / update / remove book"| P7
    P7 -->|"read / write catalogue"| D2
    P7 -->|"inventory confirmation"| A

    A -->|"view orders, set status"| P8
    P8 -->|"read / update"| D4
    P8 -->|"order list + status result"| A

    A -->|"create / edit / deactivate code"| P9
    P9 -->|"read / write"| D5
    P9 -->|"promotion confirmation"| A

    A -->|"request dashboard report"| P10
    P10 -->|"aggregate sales"| D4
    P10 -->|"top sellers"| D2
    P10 -->|"customer statistics"| D1
    P10 -->|"analytics report"| A
```

## Process catalogue

| # | Process | Inputs | Outputs | Data stores |
|---|---|---|---|---|
| 1.0 | Browse / Search Catalogue | search & filter criteria (Customer) | filtered book list | **D2** Books (read) |
| 2.0 | View Details / Submit Review | book selection; new review (Customer) | details page w/ reviews & rating | **D2**, **D3** Reviews |
| 3.0 | Manage Cart | add/remove/qty (Customer) | updated cart & totals | **D2** (stock read), **D6** Cart* |
| 4.0 | Checkout & Create Order | checkout + promo (Customer) | stored order, payment request, confirmation | **D1**, **D4**, **D5**, **D6** |
| 5.0 | Process Payment | payment request (from 4.0) | payment status (to 4.0) | none (external gateway) |
| 6.0 | Send Email Notification | confirmation payload (from 4.0) | order e-mail via Email Service | none (external) |
| 7.0 | Manage Inventory | CRUD commands (Admin) | updated `books` | **D2** |
| 8.0 | Fulfil Orders | status updates (Admin) | updated `orders` | **D4** |
| 9.0 | Manage Promotions | promotion CRUD (Admin) | updated `promotions` | **D5** |
| 10.0 | View Analytics | report request (Admin) | dashboard report | **D1**, **D2**, **D4** |

\* **D6 Cart** is session state (PHP `$_SESSION`), not a MySQL table — kept visible in the DFD because data *does* flow and must be stored somewhere between requests.

## Data store dictionary

| Store | Contents | Written by | Read by |
|---|---|---|---|
| D1 Users | accounts, roles, profiles | 4.0 (register/profile) | 4.0, 10.0 |
| D2 Books | catalogue (title, author, genre, price, stock, ISBN) | 7.0 | 1.0, 2.0, 3.0, 10.0 |
| D3 Reviews | ratings & comments | 2.0 | 2.0 |
| D4 Orders / Order Details | order headers + line items | 4.0 | 4.0, 8.0, 10.0 |
| D5 Promotions | discount codes, validity | 9.0 | 4.0 |
| D6 Cart | session basket | 3.0 | 3.0, 4.0 |
