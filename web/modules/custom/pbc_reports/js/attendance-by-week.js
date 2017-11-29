(function ($, Drupal, drupalSettings) {
Drupal.behaviors.HighChartsBehavior = {
  attach: function (context, settings) {
    // Vars from drupalSettings.
    var settingsLabels = drupalSettings.highCharts.labels;
    var settingsSeriesData = drupalSettings.highCharts.seriesData;

    Highcharts.chart('container', {
        title: {
            text: 'CG Attendance'
        },
        subtitle: {
            text: 'Based on active group members.'
        },
        xAxis: {
            categories: settingsLabels
        },
        yAxis: {
            title: {
                text: 'Percent in Attendance',
            },
            max: 100,
            min: 0,
     labels: {
        formatter: function() {
           return this.value + '%';
        }
      },
        },
        legend: {
            layout: 'vertical',
            align: 'right',
            verticalAlign: 'middle'
        },

        tooltip: {
          // formatter: function () {
          //     return this.x + ': ' + this.y + '%' + '</br>' + this.point.extra;
          // }
          shared: false,
          formatter: function() {
            // Get extra data.
            var extra = this.series.options.extra;
            // Find index of the current point.
            var index = this.series.data.indexOf(this.point);
            var display = '<b>' + this.x + '</b><br/>' + this.y + '%' + ' - ' + extra[index];
            return display;
          }
        },
        series: settingsSeriesData,

        responsive: {
            rules: [{
                condition: {
                    maxWidth: 1200
                },
                chartOptions: {
                    legend: {
                        layout: 'horizontal',
                        align: 'center',
                        verticalAlign: 'bottom'
                    }
                }
            }]
        }
    });
  }
};
})(jQuery, Drupal, drupalSettings);
