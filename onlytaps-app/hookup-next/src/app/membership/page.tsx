import { Check, Gem, Crown } from 'lucide-react';
import { getUser } from '@/lib/auth';
import { getMembership, listPlans, activeListingCount, featuredUsedThisMonth } from '@/lib/membership';
import { stripeConfigured } from '@/lib/stripe';
import { cancelSubscription, startCheckout } from './actions';

export const metadata = { title: 'Membership · Turnpage' };

const NOTES: Record<string, { cls: string; text: string }> = {
  success: { cls: 'alert-success', text: 'Payment received. Your plan activates within a few seconds — refresh if it hasn’t yet.' },
  cancelled: { cls: 'alert-info', text: 'Checkout cancelled. You haven’t been charged.' },
  cancel_scheduled: { cls: 'alert-info', text: 'Your subscription will end at the close of the current billing period.' },
  unconfigured: { cls: 'alert-warning', text: 'Online payments are not enabled yet. Contact support to upgrade.' },
  error: { cls: 'alert-error', text: 'Something went wrong with the payment provider. Please try again.' },
  invalid: { cls: 'alert-error', text: 'Unknown plan.' },
};

export default async function Membership({ searchParams }: { searchParams: Promise<{ status?: string }> }) {
  const { status } = await searchParams;
  const user = await getUser();
  const plans = await listPlans();
  const m = user ? await getMembership(user.id) : null;
  const [used, featured] = user ? await Promise.all([activeListingCount(user.id), featuredUsedThisMonth(user.id)]) : [0, 0];
  const note = status ? NOTES[status] : undefined;
  const payments = stripeConfigured();

  return (
    <div className="page">
      <section className="hero">
        <span className="hero-eyebrow"><Gem className="mr-1 inline size-3.5" />Membership</span>
        <h1 className="hero-title">Post more. <span className="hero-accent">Get seen first.</span></h1>
        <p className="hero-sub">Upgrade for more active ads, featured placement and a verified member badge.</p>
      </section>

      {note && <div className={note.cls}>{note.text}</div>}

      {user && m && (
        <div className="form-card mb-6">
          <p>
            <strong>Your plan: {m.plan.name}</strong>
            {m.expires && <span className="muted"> · renews/ends {m.expires.toLocaleDateString('en-US', { timeZone: 'UTC' })}</span>}
          </p>
          <p className="muted mt-1">
            Active ads: {used} / {m.plan.maxListings}
            {m.plan.featuredPerMonth > 0 && <> · Featured this month: {featured} / {m.plan.featuredPerMonth}</>}
          </p>
          {m.hasSubscription && (
            <form action={cancelSubscription} className="mt-3">
              <button className="btn btn-secondary btn-sm" type="submit">Cancel subscription</button>
            </form>
          )}
        </div>
      )}

      <div className="grid gap-5 md:grid-cols-3">
        {plans.map((p) => {
          const current = (m?.plan.slug ?? 'free') === p.slug;
          const best = p.slug === 'plus';
          return (
            <div key={p.id} className={'rounded-3xl border bg-white/5 p-6 backdrop-blur ' + (best ? 'border-violet-400/60 shadow-glow' : 'border-white/10')}>
              {best && <span className="hero-eyebrow">Most popular</span>}
              <h2 className="flex items-center gap-2 text-xl font-bold">{p.slug === 'vip' && <Crown className="size-5 text-amber-300" />}{p.slug === 'plus' && <Gem className="size-5 text-violet-300" />}{p.name}</h2>
              <p className="mt-2 text-4xl font-extrabold">
                {p.price > 0 ? `$${p.price.toFixed(2)}` : 'Free'}
                {p.price > 0 && <span className="text-base font-medium text-white/60"> /mo</span>}
              </p>
              <p className="muted mt-1">{p.description}</p>
              <ul className="my-5 space-y-2 text-sm text-white/80">
                {p.features.map((f) => <li key={f} className="flex items-start gap-2"><Check className="mt-0.5 size-4 shrink-0 text-violet-300" />{f}</li>)}
              </ul>
              {current ? (
                <span className="btn btn-secondary w-full cursor-default">Current plan</span>
              ) : p.slug === 'free' ? null : user ? (
                <form action={startCheckout}>
                  <input type="hidden" name="plan" value={p.slug} />
                  <button className="btn btn-primary w-full" type="submit" disabled={!payments}>
                    {payments ? `Get ${p.name}` : 'Payments coming soon'}
                  </button>
                </form>
              ) : (
                <a className="btn btn-primary w-full" href="/login.php?redirect=/membership.php">Log in to upgrade</a>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
