# The money

What a subscriber actually costs, what one is actually worth, and where the
margin goes. Numbers are from the live configuration, not estimates of a
different product.

---

## What €8 becomes

The landing page promises €8 a month. Two deductions happen before any of it
reaches you.

**VAT.** Selling to EU consumers, the displayed price is tax-inclusive — legally
required in most member states, and what a customer expects when a page says
€8. At Portugal's 23%, €8 inclusive is **€6.50** before anything else.

**Paddle.** As Merchant of Record they handle VAT registration, filing and
remittance across every jurisdiction — genuinely valuable, and priced at
**5% + €0.50 per transaction**. On €6.50 that is €0.83.

| Displayed | Ex-VAT | Paddle fee | **Net to you** | Fee as % of displayed |
|---|---|---|---|---|
| €8 | €6.50 | €0.83 | **€5.68** | 29% total |
| €9 | €7.32 | €0.87 | **€6.45** | 28% |
| €10 | €8.13 | €0.91 | **€7.22** | 28% |

The fixed €0.50 is what hurts at this price point: it is 6% of €8 on its own.

---

## What a subscriber costs

**Per user, per month — the surprise is how small it is.**

| Item | Cost | Working |
|---|---|---|
| AI analysis | **€0.014** | Measured prompt is ~1,500 tokens; with the system prompt and a ~1,000-token reply that is ~€0.0017 per analysis on a cheap model. At 8 analyses a month (the cap is 30) that is under two cents. |
| Photo storage | **€0.0015** | ~100 MB on R2 at €0.015/GB. At the 500-photo cap, still €0.014. |
| Egress | **€0** | R2 charges nothing for it, which is why R2 and not S3 — every photo view is an egress because photos are streamed through the app rather than served from a bucket URL. |
| Database, compute | **~€0** | 136 workouts is a few MB. Neon's free 0.5 GB holds 100+ athletes. |

**Total variable cost: about €0.02 per subscriber per month.** The AI allowance,
which sounds like the expensive part, is not. The 30-a-month cap is doing its
job — and an athlete on their own provider key costs nothing at all.

**Fixed costs** are what actually matter at low numbers:

| Item | Free tier | When it ends | Paid |
|---|---|---|---|
| Render | sleeps after 15 min idle | as soon as you have real users | €6.50/mo (Starter) |
| Neon | 0.5 GB | ~100+ athletes | €17.50/mo (Launch) |
| Resend | 3,000 emails/mo | ~400 athletes | €18.50/mo |
| R2 | 10 GB | ~300 athletes | €0.015/GB |
| Domain | — | — | ~€1/mo |

---

## Profit per subscriber

At €8 displayed (€5.68 net), with a paid Render instance and a domain:

| Subscribers | Revenue | Infrastructure | Profit | **Per subscriber** |
|---|---|---|---|---|
| 1 | €6 | €8 | −€2 | **−€1.84** |
| 5 | €28 | €8 | €21 | **€4.16** |
| 10 | €57 | €8 | €49 | **€4.91** |
| 20 | €114 | €8 | €106 | **€5.29** |
| 50 | €284 | €8 | €276 | **€5.51** |
| 100 | €568 | €8 | €559 | **€5.59** |
| 1,000 | €5,680 | €45 | €5,620 | **€5.62** |

At €9 displayed (€6.45 net):

| Subscribers | Profit | **Per subscriber** |
|---|---|---|
| 5 | €25 | **€4.93** |
| 10 | €57 | **€5.68** |
| 20 | €121 | **€6.06** |
| 100 | €636 | **€6.36** |

## The answer

**€8 works, but only just, and only after about twenty subscribers.** It
asymptotes at roughly €5.60 — above the €5 target, with almost no room for
anything going wrong. One refund, one chargeback, or one month where the AI
allowance is actually used to the cap eats the difference.

**€9 clears €5 from ten subscribers and settles above €6.35.** That is a real
margin: room to absorb a surprise, and room to *lower* the price later as a
deliberate move rather than as a rescue.

Three things that would change the picture more than the price:

1. **Annual billing at €90/year.** One transaction instead of twelve saves
   eleven €0.50 fees — €5.50 a year — and cuts churn, which matters more than
   the fee. Net works out around €5.75/month.
2. **Leaving the AI allowance where it is.** The instinct will be to raise the
   cap as a selling point. It is the only cost that scales with enthusiasm, and
   the honest framing already exists: bring your own key for unlimited.
3. **Not moving off free tiers early.** Neon, Resend and R2 free tiers cover the
   first hundred subscribers. Only Render needs paying for on day one, and only
   because the free one sleeps.

## What is not in these numbers

Your time. At €5.60 a subscriber, twenty subscribers is €112 a month — which is
a hobby that pays for itself, not an income. The business case only becomes
interesting in the hundreds, and that is a marketing problem rather than an
engineering one.
