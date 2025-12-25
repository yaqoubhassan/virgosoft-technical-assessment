# Limit Order Exchange Mini Engine

A full-stack limit order exchange application built with Laravel (API) and Vue.js (Frontend).

## Features

- User authentication (Register, Login, Logout)
- USD balance and crypto asset management
- Limit order placement (Buy/Sell)
- Order matching engine with full match support
- 1.5% trading commission
- Real-time updates via Pusher
- Order cancellation with fund release
- Orderbook display
- Order history with filtering

## Tech Stack

### Backend

- Laravel 12.x
- PHP 8.4
- MySQL/PostgreSQL
- Laravel Sanctum (API Authentication)
- Pusher (Real-time Broadcasting)

### Frontend

- Vue.js 3 (Composition API)
- Tailwind CSS 4.x
- Pinia (State Management)
- Vue Router
- Laravel Echo + Pusher.js

## Project Structure

```
├── backend/          # Laravel API
│   ├── app/
│   │   ├── Events/          # Broadcast events
│   │   ├── Http/Controllers/ # API controllers
│   │   ├── Models/          # Eloquent models
│   │   └── Services/        # Business logic
│   ├── database/
│   │   └── migrations/      # Database migrations
│   └── routes/
│       ├── api.php          # API routes
│       └── channels.php     # Broadcasting channels
│
├── frontend/         # Vue.js SPA
│   └── src/
│       ├── components/      # Vue components
│       ├── composables/     # Composable functions
│       ├── router/          # Vue Router
│       ├── stores/          # Pinia stores
│       └── views/           # Page views
```

## Setup Instructions

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL or PostgreSQL
- Pusher account (for real-time features)

### Backend Setup

1. Navigate to the backend directory:

   ```bash
   cd backend
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Copy environment file:

   ```bash
   cp .env.example .env
   ```

4. Configure your `.env` file:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=limit_order_exchange
   DB_USERNAME=root
   DB_PASSWORD=

   BROADCAST_CONNECTION=pusher

   PUSHER_APP_ID=your-app-id
   PUSHER_APP_KEY=your-app-key
   PUSHER_APP_SECRET=your-app-secret
   PUSHER_APP_CLUSTER=mt1

   FRONTEND_URL=http://localhost:5173
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173
   ```

5. Generate application key:

   ```bash
   php artisan key:generate
   ```

6. Create the database:

   ```bash
   mysql -u root -e "CREATE DATABASE limit_order_exchange"
   ```

7. Run migrations:

   ```bash
   php artisan migrate
   ```

8. Start the development server:
   ```bash
   php artisan serve
   ```

### Frontend Setup

1. Navigate to the frontend directory:

   ```bash
   cd frontend
   ```

2. Install dependencies:

   ```bash
   npm install
   ```

3. Create `.env` file:

   ```bash
   cp .env.example .env
   ```

4. Configure Pusher keys in `.env`:

   ```env
   VITE_PUSHER_APP_KEY=your-app-key
   VITE_PUSHER_APP_CLUSTER=mt1
   ```

5. Start the development server:

   ```bash
   npm run dev
   ```

6. Open your browser and navigate to `http://localhost:5173`

## API Endpoints

| Method | Endpoint                | Description                              |
| ------ | ----------------------- | ---------------------------------------- |
| POST   | /api/register           | Register a new user                      |
| POST   | /api/login              | User login                               |
| POST   | /api/logout             | User logout                              |
| GET    | /api/user               | Get authenticated user                   |
| GET    | /api/profile            | Get user profile with balance and assets |
| GET    | /api/orders             | Get orderbook (all open orders)          |
| GET    | /api/my-orders          | Get user's orders                        |
| POST   | /api/orders             | Create a new order                       |
| POST   | /api/orders/{id}/cancel | Cancel an open order                     |

## Business Logic

### Order Creation

**Buy Order:**

1. Check if user has sufficient USD balance
2. Deduct total cost (price × amount) from user balance
3. Create order with status "open"
4. Attempt to match with existing sell orders

**Sell Order:**

1. Check if user has sufficient asset balance
2. Lock the asset amount for the order
3. Create order with status "open"
4. Attempt to match with existing buy orders

### Order Matching

- **Full match only** - Orders must match exactly in amount
- Buy orders match with sell orders where `sell.price <= buy.price`
- Sell orders match with buy orders where `buy.price >= sell.price`
- Execution price is the maker (resting order) price
- Priority: Price (best price first), then time (oldest first)

### Commission

- **Rate:** 1.5% of the matched USD value
- Commission is deducted from the buyer
- Example: 0.01 BTC @ $95,000 = $950 value → $14.25 commission

### Order Cancellation

- Only open orders can be cancelled
- Buy orders: Return locked USD to user balance
- Sell orders: Release locked assets back to available

## Real-Time Updates

When an order is matched:

1. Backend broadcasts `OrderMatched` event to both buyer and seller
2. Event is sent via private Pusher channel (`private-user.{id}`)
3. Frontend receives the event and updates:
   - Wallet balances
   - Asset amounts
   - Order status
   - Orderbook

## Database Schema

### users

- id, name, email, password, balance (USD), timestamps

### assets

- id, user_id, symbol, amount, locked_amount, timestamps

### orders

- id, user_id, symbol, side, price, amount, locked_usd, status, timestamps

### trades

- id, buy_order_id, sell_order_id, buyer_id, seller_id, symbol, price, amount, total, commission, timestamps

## Testing the Application

1. Register two different user accounts
2. User A: Place a sell order (e.g., Sell 0.1 BTC @ $50,000)
3. User B: Place a matching buy order (e.g., Buy 0.1 BTC @ $50,000)
4. Observe real-time updates on both accounts
5. Check the order history and wallet balances

## Development Notes

- The application uses database transactions to ensure atomic operations
- Row locking is implemented to prevent race conditions
- Bcmath functions are used for precise decimal calculations
- CORS is configured for the frontend URL

## License

This project was created for a technical assessment.
