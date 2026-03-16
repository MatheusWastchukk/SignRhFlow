import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '../services/api.service';
import { SigningContextResponse } from '../models';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';
import { firstValueFrom } from 'rxjs';

@Component({
  selector: 'app-signing-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './signing-page.component.html',
})
export class SigningPageComponent implements OnInit, OnDestroy {
  readonly countryOptions = [
    { code: 'BR', label: 'Brasil (+55)', dialCode: '+55', mask: '(99) 99999-9999' },
    { code: 'US', label: 'Estados Unidos (+1)', dialCode: '+1', mask: '(999) 999-9999' },
    { code: 'PT', label: 'Portugal (+351)', dialCode: '+351', mask: '999 999 999' },
  ] as const;

  loading = true;
  error = '';
  formError = '';
  savingSignerData = false;
  signingToken = '';
  showSignerModal = true;
  showSignatureModal = false;
  showDeliveryModal = false;
  selectedDeliveryMethod: 'EMAIL' | 'WHATSAPP' = 'EMAIL';
  signatureDraft = '';
  signedName = '';
  savingSignature = false;
  finalizing = false;
  finalizeError = '';
  deliveryError = '';
  finalizeSuccess = '';
  pdfViewerUrl: SafeResourceUrl | null = null;
  pdfPageCount = 1;
  context: SigningContextResponse | null = null;
  private readonly emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
  readonly signerForm = this.formBuilder.group({
    name: ['', [Validators.required]],
    email: ['', [Validators.required, Validators.pattern(this.emailPattern)]],
    cpf: ['', [Validators.required]],
    phone_country: ['BR' as 'BR' | 'US' | 'PT', [Validators.required]],
    phone: ['', [Validators.required]],
  });

  constructor(
    private readonly route: ActivatedRoute,
    private readonly apiService: ApiService,
    private readonly formBuilder: FormBuilder,
    private readonly sanitizer: DomSanitizer
  ) {}

  ngOnInit(): void {
    const token = this.route.snapshot.paramMap.get('token');

    if (!token) {
      this.loading = false;
      this.error = 'Link de assinatura invalido.';
      this.setSignerModalVisible(false);
      return;
    }

    this.signingToken = token;
    this.setSignerModalVisible(true);

    this.apiService.getSigningContext(token).subscribe({
      next: (context) => {
        this.context = context;
        this.pdfViewerUrl = this.sanitizer.bypassSecurityTrustResourceUrl(context.contract.pdf_url);
        this.detectPdfPageCount(context.contract.pdf_url);
        this.setSignerModalVisible(true);
        this.signerForm.reset({
          name: '',
          email: '',
          cpf: '',
          phone_country: 'BR',
          phone: '',
        });
        this.loading = false;
      },
      error: (errorResponse) => {
        this.error = errorResponse?.error?.message || 'Nao foi possivel carregar o contexto de assinatura.';
        this.loading = false;
      }
    });
  }

  submitSignerData(): void {
    if (!this.signerForm.valid) {
      this.signerForm.markAllAsTouched();
      return;
    }

    if (!this.signingToken) {
      this.formError = 'Token de assinatura invalido.';
      return;
    }

    this.savingSignerData = true;
    this.formError = '';
    const value = this.signerForm.getRawValue();
    const country = (value.phone_country ?? 'BR') as 'BR' | 'US' | 'PT';
    if (!this.isPhoneComplete(country, value.phone ?? '')) {
      this.savingSignerData = false;
      this.formError = 'Telefone incompleto para o país selecionado.';
      return;
    }

    this.apiService.saveSignerData(this.signingToken, {
      name: value.name ?? '',
      email: (value.email ?? '').toLowerCase(),
      cpf: this.normalizeCpfDigits(value.cpf ?? ''),
      phone_country: country,
      phone: this.toE164(country, value.phone ?? ''),
    }).subscribe({
      next: () => {
        this.setSignerModalVisible(false);
        this.savingSignerData = false;
        this.signatureDraft = this.signerForm.getRawValue().name ?? '';
      },
      error: (errorResponse) => {
        const errors = errorResponse?.error?.errors;
        if (errors && typeof errors === 'object') {
          const firstField = Object.keys(errors)[0];
          const firstMessage = Array.isArray(errors[firstField]) ? errors[firstField][0] : '';
          this.formError = firstMessage || 'Falha ao salvar dados do signatario.';
        } else {
          this.formError = errorResponse?.error?.message || 'Falha ao salvar dados do signatario.';
        }
        this.savingSignerData = false;
      }
    });
  }

  ngOnDestroy(): void {
    this.unlockPageScroll();
  }

  openSignatureModal(): void {
    if (this.showSignerModal) {
      return;
    }

    this.showSignatureModal = true;
    this.finalizeError = '';
  }

  cancelSignatureModal(): void {
    this.showSignatureModal = false;
  }

  async confirmSignatureModal(): Promise<void> {
    const signature = (this.signatureDraft ?? '').trim();
    if (!signature) {
      this.finalizeError = 'Digite a assinatura antes de confirmar.';
      return;
    }

    if (!this.signingToken) {
      this.finalizeError = 'Token de assinatura invalido.';
      return;
    }

    this.savingSignature = true;
    this.finalizeError = '';

    try {
      await firstValueFrom(this.apiService.signContract(this.signingToken, {
        signed_name: signature,
      }));
      this.signedName = signature;
      this.showSignatureModal = false;
    } catch (error: any) {
      this.finalizeError = error?.error?.message || 'Falha ao confirmar assinatura.';
    } finally {
      this.savingSignature = false;
    }
  }

  get canFinalize(): boolean {
    return !!this.context && !this.showSignerModal && this.signedName.trim().length > 0;
  }

  openFinalizeModal(): void {
    if (!this.context || !this.canFinalize || this.finalizing) {
      return;
    }

    this.selectedDeliveryMethod = this.context.contract.delivery_method ?? 'EMAIL';
    this.deliveryError = '';
    this.showDeliveryModal = true;
  }

  cancelFinalizeModal(): void {
    this.showDeliveryModal = false;
    this.deliveryError = '';
  }

  async confirmFinalizeModal(): Promise<void> {
    if (!this.selectedDeliveryMethod) {
      this.deliveryError = 'Escolha o canal de recebimento para concluir.';
      return;
    }

    this.showDeliveryModal = false;
    await this.finalizeAndDownloadSignedPdf(this.selectedDeliveryMethod);
  }

  async finalizeAndDownloadSignedPdf(deliveryMethod: 'EMAIL' | 'WHATSAPP'): Promise<void> {
    if (!this.context || !this.canFinalize || this.finalizing) {
      return;
    }

    this.finalizing = true;
    this.finalizeError = '';
    this.finalizeSuccess = '';

    try {
      const response = await fetch(this.context.contract.pdf_url);
      if (!response.ok) {
        throw new Error('Falha ao carregar PDF original.');
      }

      const originalBytes = await response.arrayBuffer();
      const pdfDoc = await PDFDocument.load(originalBytes);
      const pages = pdfDoc.getPages();
      const targetPage = pages[pages.length - 1];
      const { width } = targetPage.getSize();
      const font = await pdfDoc.embedFont(StandardFonts.HelveticaOblique);
      const signatureText = this.signedName;
      const signatureSize = 22;
      const signatureWidth = font.widthOfTextAtSize(signatureText, signatureSize);
      const signatureX = Math.max(40, (width - signatureWidth) / 2);

      targetPage.drawText(signatureText, {
        x: signatureX,
        y: 80,
        size: signatureSize,
        font,
        color: rgb(0.08, 0.14, 0.31),
      });

      const stampText = `Assinado digitalmente em ${new Date().toLocaleString('pt-BR')}`;
      const stampSize = 10;
      const stampWidth = font.widthOfTextAtSize(stampText, stampSize);
      targetPage.drawText(`Assinado digitalmente em ${new Date().toLocaleString('pt-BR')}`, {
        x: Math.max(40, (width - stampWidth) / 2),
        y: 52,
        size: stampSize,
        font,
        color: rgb(0.3, 0.33, 0.4),
      });

      const signedBytes = await pdfDoc.save();
      const blob = new Blob([signedBytes], { type: 'application/pdf' });
      const objectUrl = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = objectUrl;
      anchor.download = `contrato-assinado-${this.context.contract.id}.pdf`;
      anchor.click();
      URL.revokeObjectURL(objectUrl);

      const finalizeResponse = await firstValueFrom(this.apiService.finalizeSigning(this.signingToken, {
        signed_name: this.signedName,
        delivery_method: deliveryMethod,
      }));

      this.finalizeSuccess = 'Assinatura finalizada e documento assinado baixado.';
      if (this.context) {
        this.context.contract.status = 'SIGNED';
        this.context.contract.signed_at = finalizeResponse.signed_at ?? new Date().toISOString();
        this.context.contract.delivery_method = finalizeResponse.delivery_method ?? deliveryMethod;
      }
    } catch (error) {
      this.finalizeError = error instanceof Error ? error.message : 'Falha ao finalizar assinatura.';
    } finally {
      this.finalizing = false;
    }
  }

  get pdfViewerWrapperClass(): string {
    if (this.pdfPageCount > 1) {
      return 'h-[1120px] overflow-y-auto';
    }

    return 'h-[1120px] overflow-hidden';
  }

  onPhoneCountryChange(): void {
    const country = (this.signerForm.get('phone_country')?.value ?? 'BR') as 'BR' | 'US' | 'PT';
    const currentDigits = this.normalizePhoneDigits(this.signerForm.get('phone')?.value ?? '');
    this.signerForm.patchValue({
      phone: this.maskPhoneForCountry(currentDigits, country),
    });
  }

  onPhoneInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const country = (this.signerForm.get('phone_country')?.value ?? 'BR') as 'BR' | 'US' | 'PT';
    input.value = this.maskPhoneForCountry(this.normalizePhoneDigits(input.value), country);
    this.signerForm.patchValue({ phone: input.value }, { emitEvent: false });
  }

  onCpfInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    input.value = this.maskCpf(this.normalizeCpfDigits(input.value));
    this.signerForm.patchValue({ cpf: input.value }, { emitEvent: false });
  }

  private normalizePhoneDigits(value: string): string {
    return (value ?? '').replace(/\D/g, '');
  }

  private normalizeCpfDigits(value: string): string {
    return (value ?? '').replace(/\D/g, '').slice(0, 11);
  }

  private maskCpf(digits: string): string {
    const list = digits.slice(0, 11);
    if (list.length <= 3) return list;
    if (list.length <= 6) return `${list.slice(0, 3)}.${list.slice(3)}`;
    if (list.length <= 9) return `${list.slice(0, 3)}.${list.slice(3, 6)}.${list.slice(6)}`;
    return `${list.slice(0, 3)}.${list.slice(3, 6)}.${list.slice(6, 9)}-${list.slice(9, 11)}`;
  }

  private maskPhoneForCountry(digits: string, country: 'BR' | 'US' | 'PT'): string {
    const list = digits.slice(0, country === 'BR' ? 11 : country === 'US' ? 10 : 9);

    if (country === 'BR') {
      if (list.length <= 2) return list;
      if (list.length <= 7) return `(${list.slice(0, 2)}) ${list.slice(2)}`;
      return `(${list.slice(0, 2)}) ${list.slice(2, 7)}-${list.slice(7, 11)}`;
    }

    if (country === 'US') {
      if (list.length <= 3) return list;
      if (list.length <= 6) return `(${list.slice(0, 3)}) ${list.slice(3)}`;
      return `(${list.slice(0, 3)}) ${list.slice(3, 6)}-${list.slice(6, 10)}`;
    }

    if (list.length <= 3) return list;
    if (list.length <= 6) return `${list.slice(0, 3)} ${list.slice(3)}`;
    return `${list.slice(0, 3)} ${list.slice(3, 6)} ${list.slice(6, 9)}`;
  }

  private toE164(country: 'BR' | 'US' | 'PT', maskedPhone: string): string {
    const digits = this.normalizePhoneDigits(maskedPhone);
    const dialCode = this.countryOptions.find((item) => item.code === country)?.dialCode ?? '+55';
    const countryDigits = dialCode.replace('+', '');
    return `+${countryDigits}${digits}`;
  }

  private isPhoneComplete(country: 'BR' | 'US' | 'PT', maskedPhone: string): boolean {
    const digits = this.normalizePhoneDigits(maskedPhone);
    if (country === 'BR') return digits.length === 10 || digits.length === 11;
    if (country === 'US') return digits.length === 10;
    return digits.length === 9;
  }

  private async detectPdfPageCount(pdfUrl: string): Promise<void> {
    try {
      const response = await fetch(pdfUrl);
      if (!response.ok) {
        return;
      }
      const bytes = await response.arrayBuffer();
      const doc = await PDFDocument.load(bytes);
      this.pdfPageCount = doc.getPageCount();
    } catch {
      this.pdfPageCount = 1;
    }
  }

  private setSignerModalVisible(visible: boolean): void {
    this.showSignerModal = visible;
    if (visible) {
      document.body.style.overflow = 'hidden';
      return;
    }

    this.unlockPageScroll();
  }

  private unlockPageScroll(): void {
    document.body.style.overflow = '';
  }
}
