import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ApiService } from '../services/api.service';

@Component({
  selector: 'app-new-contract-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './new-contract-page.component.html',
})
export class NewContractPageComponent {
  readonly countryOptions = [
    { code: 'BR', label: 'Brasil (+55)', dialCode: '+55' },
    { code: 'US', label: 'Estados Unidos (+1)', dialCode: '+1' },
    { code: 'PT', label: 'Portugal (+351)', dialCode: '+351' },
  ] as const;

  loading = false;
  success = '';
  error = '';
  private readonly emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;

  readonly form = this.formBuilder.group({
    name: ['', [Validators.required]],
    email: ['', [Validators.required, Validators.pattern(this.emailPattern)]],
    phone_country: ['BR' as 'BR' | 'US' | 'PT', [Validators.required]],
    phone: ['', [Validators.required]],
    cpf: ['', [Validators.required]],
    delivery_method: ['EMAIL' as 'EMAIL' | 'WHATSAPP', [Validators.required]]
  });

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly apiService: ApiService,
    private readonly router: Router
  ) {}

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading = true;
    this.success = '';
    this.error = '';

    const value = this.form.getRawValue();
    const country = (value.phone_country ?? 'BR') as 'BR' | 'US' | 'PT';
    if (!this.isPhoneComplete(country, value.phone ?? '')) {
      this.loading = false;
      this.error = 'Telefone incompleto para o país selecionado.';
      return;
    }

    this.apiService
      .createEmployee({
        name: value.name ?? '',
        email: (value.email ?? '').toLowerCase(),
        phone: this.toE164(country, value.phone ?? ''),
        cpf: value.cpf ?? ''
      })
      .subscribe({
        next: (employee) => {
          this.apiService
            .createContract({
              employee_id: employee.id,
              delivery_method: (value.delivery_method ?? 'EMAIL') as 'EMAIL' | 'WHATSAPP'
            })
            .subscribe({
              next: () => {
                this.loading = false;
                this.success = 'Contrato criado e enviado para processamento.';
                this.form.reset({ delivery_method: 'EMAIL', phone_country: 'BR' });
                setTimeout(() => {
                  this.router.navigateByUrl('/dashboard');
                }, 800);
              },
              error: () => {
                this.loading = false;
                this.error = 'Falha ao criar contrato.';
              }
            });
        },
        error: (errorResponse) => {
          this.loading = false;
          this.error = this.extractErrorMessage(errorResponse) || 'Falha ao criar colaborador.';
        }
      });
  }

  private extractErrorMessage(errorResponse: any): string {
    const errors = errorResponse?.error?.errors;
    if (errors && typeof errors === 'object') {
      const firstField = Object.keys(errors)[0];
      const firstMessage = Array.isArray(errors[firstField]) ? errors[firstField][0] : null;
      if (typeof firstMessage === 'string' && firstMessage.length > 0) {
        return firstMessage;
      }
    }

    return errorResponse?.error?.message ?? '';
  }

  onPhoneCountryChange(): void {
    const country = (this.form.get('phone_country')?.value ?? 'BR') as 'BR' | 'US' | 'PT';
    const currentDigits = this.normalizePhoneDigits(this.form.get('phone')?.value ?? '');
    this.form.patchValue({
      phone: this.maskPhoneForCountry(currentDigits, country),
    });
  }

  onPhoneInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const country = (this.form.get('phone_country')?.value ?? 'BR') as 'BR' | 'US' | 'PT';
    input.value = this.maskPhoneForCountry(this.normalizePhoneDigits(input.value), country);
    this.form.patchValue({ phone: input.value }, { emitEvent: false });
  }

  private normalizePhoneDigits(value: string): string {
    return (value ?? '').replace(/\D/g, '');
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
}
