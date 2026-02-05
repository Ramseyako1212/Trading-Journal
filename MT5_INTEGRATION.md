# MetaTrader 5 (MT5) Integration Guide

This guide explains how to automatically sync your Exness/MT5 trades with your Trading Journal.

## Step 1: Generate your API Key
1. Go to **Settings** in your Trading Journal.
2. Scroll to the **Auto-Sync Integrations** section.
3. Click **Generate New Key**.
4. Copy your **API Key** and **Webhook URL**.

## Step 2: Configure MetaTrader 5
1. Open MT5.
2. Go to **Tools » Options » Expert Advisors**.
3. Check the box **Allow WebRequest for listed URL**.
4. Add your domain/URL (e.g., `http://localhost`).

## Step 3: Copy the Expert Advisor (EA) Code
Create a new Expert Advisor in MetaEditor (F4) and paste the following code:

```cpp
#property copyright "Trading Journal Sync"
#property version   "1.05"
#property strict

// --- PASTE YOUR API KEY FROM THE SETTINGS PAGE BELOW ---
input string InpApiKey = "YOUR_API_KEY_HERE"; 
// --- I HAVE FILLED YOUR LOCAL URL FOR YOU ---
input string InpUrl = "http://127.0.0.1/TRADING-JOURNAL/api/trades/webhook_mt5.php";

datetime last_check;

int OnInit() {
   // Looking back 24 hours to sync any missed trades
   last_check = TimeCurrent() - 86400; 
   EventSetTimer(20); // Check every 20 seconds
   return(INIT_SUCCEEDED);
}

void OnDeinit(const int reason) { EventKillTimer(); }

void OnTimer() { CheckForClosedTrades(); }

void CheckForClosedTrades() {
   if(!HistorySelect(last_check, TimeCurrent())) return;
   
   int total = HistoryDealsTotal();
   for(int i=0; i<total; i++) {
      ulong ticket = HistoryDealGetTicket(i);
      if(ticket <= 0) continue;
      if(HistoryDealGetInteger(ticket, DEAL_ENTRY) != (long)DEAL_ENTRY_OUT) continue;
      
      long type      = HistoryDealGetInteger(ticket, DEAL_TYPE);
      string symbol  = HistoryDealGetString(ticket, DEAL_SYMBOL);
      double volume  = HistoryDealGetDouble(ticket, DEAL_VOLUME);
      double price   = HistoryDealGetDouble(ticket, DEAL_PRICE);
      double profit  = HistoryDealGetDouble(ticket, DEAL_PROFIT);
      double comm    = HistoryDealGetDouble(ticket, DEAL_COMMISSION);
      double swap    = HistoryDealGetDouble(ticket, DEAL_SWAP);
      datetime exit_time = (datetime)HistoryDealGetInteger(ticket, DEAL_TIME);
      
      double entry_price = 0;
      datetime entry_time = 0;
      ulong position_id = HistoryDealGetInteger(ticket, DEAL_POSITION_ID);
      
      if(HistorySelectByPosition(position_id)) {
         for(int j=0; j<HistoryDealsTotal(); j++) {
            ulong t = HistoryDealGetTicket(j);
            if(HistoryDealGetInteger(t, DEAL_ENTRY) == (long)DEAL_ENTRY_IN) {
               entry_price = HistoryDealGetDouble(t, DEAL_PRICE);
               entry_time = (datetime)HistoryDealGetInteger(t, DEAL_TIME);
               break;
            }
         }
      }

      // Fallback if entry match fails
      if(entry_time == 0) entry_time = exit_time;

      string json = StringFormat("{\"api_key\":\"%s\",\"account_id\":%d,\"trade\":{\"ticket\":%llu,\"symbol\":\"%s\",\"type\":%d,\"entry_price\":%.5f,\"exit_price\":%.5f,\"volume\":%.2f,\"profit\":%.2f,\"commission\":%.2f,\"swap\":%.2f,\"entry_time\":\"%s\",\"exit_time\":\"%s\",\"stop_loss\":%.5f,\"take_profit\":%.5f}}",
         InpApiKey, (int)AccountInfoInteger(ACCOUNT_LOGIN), ticket, symbol, (int)type, entry_price, price, volume, profit, comm, swap, 
         TimeToString(entry_time, TIME_DATE|TIME_SECONDS), TimeToString(exit_time, TIME_DATE|TIME_SECONDS),
         HistoryDealGetDouble(ticket, DEAL_SL), HistoryDealGetDouble(ticket, DEAL_TP));
      
      SendToJournal(json);
   }
   last_check = TimeCurrent();
}

void SendToJournal(string json) {
   char data[], result[];
   string res_headers, headers = "Content-Type: application/json\r\n";
   StringToCharArray(json, data, 0, WHOLE_ARRAY, CP_UTF8);
   ArrayResize(data, ArraySize(data)-1);
   
   ResetLastError();
   int res = WebRequest("POST", InpUrl, headers, 5000, data, result, res_headers);
   
   if(res == -1) Print("WebRequest Error: ", GetLastError(), ". Double check Step 2 below!");
   else if(res >= 400) Print("Server responded with error: ", res);
   else Print("Trade synced successfully to Journal!");
}
```

## Troubleshooting
- **Symbols matching**: Ensure the "Instrument Code" in your journal (e.g., `XAUUSD`) matches the symbol in MT5 (the journal handles `.m` or `.pro` suffixes automatically).
- **Connection**: If running on `localhost`, ensure MT5 has access to your local server.
- **WebRequest status**: In MT5 "Journal" tab, look for any errors related to `WebRequest`. Error 4060 means you missed Step 2.
