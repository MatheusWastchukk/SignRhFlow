import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ApiService } from '../services/api.service';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-login-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './login-page.component.html',
})
export class LoginPageComponent {
  loading = false;
  error = '';

  readonly form = this.formBuilder.group({
    login: ['admin', [Validators.required]],
    password: ['admin', [Validators.required]],
  });

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly apiService: ApiService,
    private readonly authService: AuthService,
    private readonly router: Router
  ) {}

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading = true;
    this.error = '';

    const value = this.form.getRawValue();

    this.apiService.login({
      login: value.login ?? '',
      password: value.password ?? '',
    }).subscribe({
      next: (response) => {
        this.authService.setToken(response.token);
        this.loading = false;
        this.router.navigateByUrl('/dashboard');
      },
      error: (errorResponse) => {
        this.loading = false;
        this.error = errorResponse?.error?.message ?? 'Falha ao autenticar.';
      }
    });
  }
}
