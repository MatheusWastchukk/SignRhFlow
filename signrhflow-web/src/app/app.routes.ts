import { Routes } from '@angular/router';
import { DashboardPageComponent } from './pages/dashboard-page.component';
import { NewContractPageComponent } from './pages/new-contract-page.component';
import { LoginPageComponent } from './pages/login-page.component';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
  { path: 'login', component: LoginPageComponent },
  { path: 'dashboard', component: DashboardPageComponent, canActivate: [authGuard] },
  { path: 'contracts/new', component: NewContractPageComponent, canActivate: [authGuard] },
];
