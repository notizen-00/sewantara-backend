use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::withTrashed()->findOrFail('Tenant ID');

DB::connection('pgsql')->transaction(function () use ($tenant) {
$subscriptionIds = DB::table('subscriptions')
->where('subscriber_type', $tenant->getMorphClass())
->where('subscriber_id', $tenant->id)
->pluck('id');

    DB::table('subscription_payments')
        ->where('tenant_id', $tenant->id)
        ->delete();

    DB::table('subscription_invoices')
        ->where('tenant_id', $tenant->id)
        ->delete();

    DB::table('subscription_usage')
        ->whereIn('subscription_id', $subscriptionIds)
        ->delete();

    DB::table('subscriptions')
        ->whereIn('id', $subscriptionIds)
        ->delete();

    $tenant->database()->manager()->deleteDatabase($tenant);
    $tenant->forceDelete();

});
