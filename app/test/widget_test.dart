import 'package:flutter_test/flutter_test.dart';
import 'package:ctp_wirelakes_app/main.dart';
import 'package:ctp_wirelakes_app/services/api_client.dart';

void main() {
  testWidgets('CTP-WireLakes home screen renders', (WidgetTester tester) async {
    final api = ApiClient();
    await tester.pumpWidget(CtpWireLakesApp(api: api));
    expect(find.text('CTP-WireLakes'), findsWidgets);
  });
}
