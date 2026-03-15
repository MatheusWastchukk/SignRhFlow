import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { ApiService } from '../services/api.service';
import { SigningContextResponse } from '../models';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';

@Component({
  selector: 'app-signing-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './signing-page.component.html',
})
export class SigningPageComponent implements OnInit {
  loading = true;
  error = '';
  formError = '';
  savingSignerData = false;
  signingToken = '';
  showSignerModal = true;
  showSignatureModal = false;
  signatureDraft = '';
  signedName = '';
  finalizing = false;
  finalizeError = '';
  finalizeSuccess = '';
  pdfViewerUrl: SafeResourceUrl | null = null;
  context: SigningContextResponse | null = null;
  readonly signerForm = this.formBuilder.group({
    name: ['', [Validators.required]],
    email: ['', [Validators.required, Validators.email]],
    cpf: ['', [Validators.required]],
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
      return;
    }

    this.signingToken = token;

    this.apiService.getSigningContext(token).subscribe({
      next: (context) => {
        this.context = context;
        this.pdfViewerUrl = this.sanitizer.bypassSecurityTrustResourceUrl(context.contract.pdf_url);
        this.showSignerModal = true;
        if (context.signer?.name) {
          this.signerForm.patchValue({
            name: context.signer.name,
            email: context.signer.email ?? '',
            cpf: context.signer.cpf ?? '',
          });
        }
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

    this.apiService.saveSignerData(this.signingToken, {
      name: value.name ?? '',
      email: value.email ?? '',
      cpf: value.cpf ?? '',
    }).subscribe({
      next: () => {
        this.showSignerModal = false;
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

  confirmSignatureModal(): void {
    const signature = (this.signatureDraft ?? '').trim();
    if (!signature) {
      this.finalizeError = 'Digite a assinatura antes de confirmar.';
      return;
    }

    this.signedName = signature;
    this.showSignatureModal = false;
    this.finalizeError = '';
  }

  get canFinalize(): boolean {
    return !!this.context && !this.showSignerModal && this.signedName.trim().length > 0;
  }

  async finalizeAndDownloadSignedPdf(): Promise<void> {
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

      targetPage.drawText(this.signedName, {
        x: Math.max(40, width - 260),
        y: 80,
        size: 22,
        font,
        color: rgb(0.08, 0.14, 0.31),
      });

      targetPage.drawText(`Assinado digitalmente em ${new Date().toLocaleString('pt-BR')}`, {
        x: 40,
        y: 52,
        size: 10,
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

      this.finalizeSuccess = 'Assinatura finalizada e documento assinado baixado.';
    } catch (error) {
      this.finalizeError = error instanceof Error ? error.message : 'Falha ao finalizar assinatura.';
    } finally {
      this.finalizing = false;
    }
  }
}
