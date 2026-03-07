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
  loading = false;
  success = '';
  error = '';

  readonly form = this.formBuilder.group({
    name: ['', [Validators.required]],
    email: ['', [Validators.required, Validators.email]],
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

    this.apiService
      .createEmployee({
        name: value.name ?? '',
        email: value.email ?? '',
        phone: value.phone ?? '',
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
                this.form.reset({ delivery_method: 'EMAIL' });
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
}
