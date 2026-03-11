# Satoshi Tickets Plugin: includeInactive Parameter Patch

## Problem

When creating an event via Satflux (or API), BTCPay Satoshi Tickets plugin creates the event with `EventState = Disabled` (inactive) by default. The `GET /api/v1/stores/{storeId}/satoshi-tickets/events` endpoint filters events with `c.EventState == Data.EntityState.Active` only, so newly created inactive events do not appear in the list.

## Solution

Add an `includeInactive` query parameter to the GetEvents endpoint. When `true`, return all events (both Active and Disabled). When `false` or omitted, keep current behavior (only Active).

## Required Change in Plugin

**File:** `Plugins/BTCPayServer.Plugins.SatoshiTickets/Controllers/GreenfieldSatoshiTicketsEventsController.cs`

**Current code (line ~49-56):**

```csharp
[HttpGet("events")]
public async Task<IActionResult> GetEvents(string storeId, [FromQuery] bool expired = false)
{
    await using var ctx = _dbContextFactory.CreateContext();

    var eventsQuery = ctx.Events.Where(c => c.StoreId == CurrentStoreId && c.EventState == Data.EntityState.Active);
    if (expired)
        eventsQuery = eventsQuery.Where(e => e.StartDate <= DateTime.UtcNow);
```

**Replace with:**

```csharp
[HttpGet("events")]
public async Task<IActionResult> GetEvents(string storeId, [FromQuery] bool expired = false, [FromQuery] bool includeInactive = false)
{
    await using var ctx = _dbContextFactory.CreateContext();

    var eventsQuery = ctx.Events.Where(c => c.StoreId == CurrentStoreId);
    if (!includeInactive)
        eventsQuery = eventsQuery.Where(c => c.EventState == Data.EntityState.Active);
    if (expired)
        eventsQuery = eventsQuery.Where(e => e.StartDate <= DateTime.UtcNow);
```

## How to Submit

1. Fork https://github.com/TChukwuleta/BTCPayServerPlugins
2. Apply the change to `GreenfieldSatoshiTicketsEventsController.cs`
3. Open a Pull Request
4. Or create an Issue requesting this feature and link this patch

## Optional: GetEvent (single event)

The `GetEvent` endpoint (line ~80) also filters by `EventState == Active`. If you want to view/edit inactive events by ID (e.g. when expanding an inactive event in the list), update it similarly:

```csharp
var entity = ctx.Events.FirstOrDefault(c => c.Id == eventId && c.StoreId == CurrentStoreId);
```

(Remove the `&& c.EventState == Data.EntityState.Active` check so management can access inactive events.)

## Satflux Compatibility

Satflux already sends `includeInactive=true` when fetching events. Once the plugin is updated, newly created inactive events will appear in Satflux immediately.
