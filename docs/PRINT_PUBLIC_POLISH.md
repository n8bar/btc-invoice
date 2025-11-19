# Print & Public UI Polish Spec

## Goals
- Present a consistent, professional look on both the authenticated print view and the public share page.
- Surface the billing entity (user name/company) and invoice metadata clearly, leaving room for future logo branding.
- Keep QR codes legible, show a clear “rate as of” timestamp, and differentiate outstanding/disabled states so clients know whether an invoice is payable.

## Scope
1. **Header & Branding**
   - Display the invoice owner’s name (and optional company field once profile settings support it) at the top-left of both print/public views.
   - Reserve a placeholder area for a future logo upload; for now, allow custom text like “CryptoZing Invoice” that can be set per user and overridden per invoice.
   - Include contact info (email/phone) pulled from the user profile; make these fields editable per invoice before printing.

2. **Typography & Layout**
   - Tighten spacing around the summary cards so the expected/received/outstanding block fits in one column on paper.
   - Use a consistent 180px QR render (per current template constraints) and keep the caption that explains how to refresh rates before paying.
   - Ensure the “Thank you!” block and payment details don’t collapse on smaller screens; add consistent padding on public view.

3. **Status Messaging**
   - Public share page must show:
     - Active invoice: badge + “as of” timestamp + outstanding summary.
     - Disabled/expired link: friendly message + call to contact the owner (no payment details exposed).
   - Print view should display a watermark only when status = `paid`; otherwise show a subtle “Outstanding balance” note with the remaining USD/BTC amounts.

4. **User Customizable Fields**
   - Introduce profile-level defaults:
     - `billing_name`
     - `billing_address` (multi-line text)
     - `billing_email` / `billing_phone`
     - `invoice_footer_note` (e.g., “Net 7” or “Thanks for your business”)
   - Allow each invoice to override these fields (optional columns on `invoices`).
   - Expose these fields in the create/edit form under a collapsible “Branding & footer” section.

5. **Accessibility**
   - Maintain contrast ratios for text on shaded backgrounds.
   - Ensure all buttons/links on the public page have visible focus states.

## Out of Scope / Future
- Logo uploads and rich-template editor (post-MVP).
- Multi-language templates (post-MVP).

## Testing
- Update existing `InvoicePaymentDisplayTest` and `PublicShareTest` to assert the new customizable fields render.
- Add snapshot/assertions for disabled public links showing the new copy.
- Manual QA checklist for browser print preview + actual print output to verify spacing/Qr legibility.
